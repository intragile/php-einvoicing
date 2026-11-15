<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Release;

use DOMDocument;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfReader;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Oracle\Kosit\KositClient;
use IntrAgile\EInvoicing\Tests\Oracle\OfficialRules;
use IntrAgile\EInvoicing\Tests\Support\Process;
use IntrAgile\EInvoicing\Tests\Support\ReleaseInvoices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The offline release gate: our own output, checked by the official validators.
 *
 * - The XRechnung documents (UBL invoice and credit note, CII, and the extension in both
 *   syntaxes) and the XML of the XRechnung ZUGFeRD PDF must be accepted by the KoSIT validator
 *   1.6.3 with the XRechnung configuration 2026-08-31 (docker/kosit/run-release-gate.sh).
 * - The Factur-X XML of every Factur-X profile must pass the official Factur-X 1.09.2 Schematron
 *   (XSLT) through SaxonC with no failed assert and no fired report (docker/oracle/run.sh). The
 *   KoSIT configuration has no Factur-X scenario.
 * - Both ZUGFeRD PDFs must be PDF/A-3B compliant according to veraPDF (docker/verapdf/run.sh).
 *
 * The documents written by ReleaseArtefactsTest to build/release-artefacts are checked by their
 * bytes: each one must match its SHA256SUMS line, and the XML files must equal the writer output
 * for the release invoices. Each test skips when its validator is absent and fails when the
 * artefacts are missing. Every verdict is merged into build/release-gate/summary.json.
 */
#[Group('release-gate')]
final class OwnOutputGateTest extends TestCase
{
    private const string ARTEFACTS_DIR = __DIR__.'/../../build/release-artefacts';

    private const string GATE_DIR = __DIR__.'/../../build/release-gate';

    private const string VERAPDF_RUNNER = __DIR__.'/../../docker/verapdf/run.sh';

    /** Bound of the veraPDF run: the runner's own default bound (VERAPDF_TIMEOUT) plus a margin. */
    private const float VERAPDF_TIMEOUT = 1800.0 + 120.0;

    /*
     * The validator versions recorded in summary.json are not read from the validators: they are
     * bound to the pinned images, php-einvoicing-kosit:1.6.3 with the XRechnung configuration
     * 2026-08-31 (docker/kosit/Dockerfile and run-release-gate.sh) and verapdf/cli:v1.30.3
     * (docker/verapdf/run.sh), and the Factur-X 1.09.2 rule files (Fixtures). Update them together
     * with the image tags.
     */
    private const string KOSIT = 'KoSIT validator';

    private const string KOSIT_VERSION = '1.6.3, XRechnung configuration 2026-08-31';

    private const string FACTURX = 'Factur-X Schematron (XSLT)';

    private const string FACTURX_VERSION = 'Factur-X 1.09.2';

    private const string VERAPDF = 'veraPDF';

    private const string VERAPDF_VERSION = '1.30.3 (verapdf/cli image)';

    /** The veraPDF profile the PDFs must pass: PDF/A-3B, which they declare (pdfaid:part 3, conformance B). */
    private const string VERAPDF_PROFILE = 'PDF/A-3b validation profile';

    /** The case key of the XML embedded in the XRechnung ZUGFeRD PDF. */
    private const string ZUGFERD_XRECHNUNG = 'zugferd-xrechnung-pdf';

    /** The release documents written as files, by the key of ReleaseInvoices::documents(). */
    private const array XML_ARTEFACTS = [
        'xrechnung-ubl-invoice' => ReleaseArtefactsTest::UBL_XML,
        'xrechnung-ubl-creditnote' => ReleaseArtefactsTest::CREDIT_NOTE_XML,
        'xrechnung-cii' => ReleaseArtefactsTest::CII_XML,
    ];

    /** The keys of ReleaseInvoices::documents(), checked against it in the test. */
    private const array DOCUMENT_KEYS = [
        'xrechnung-ubl-invoice',
        'xrechnung-ubl-creditnote',
        'xrechnung-cii',
        'xrechnung-extension-ubl',
        'xrechnung-extension-cii',
    ];

    /**
     * Case keys only: PHPUnit builds every data provider even when the group is excluded, so the
     * documents are written and read inside the test.
     *
     * @return array<string, array{string}>
     */
    public static function kositCases(): array
    {
        $cases = [];
        foreach ([...self::DOCUMENT_KEYS, self::ZUGFERD_XRECHNUNG] as $key) {
            $cases[$key] = [$key];
        }

        return $cases;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function facturxCases(): array
    {
        $cases = [];
        foreach (FacturXProfile::cases() as $profile) {
            if ($profile->ruleProfile() !== null) {
                $cases[$profile->name] = [$profile->name];
            }
        }

        return $cases;
    }

    #[DataProvider('kositCases')]
    public function test_kosit_accepts_own_xrechnung_output(string $key): void
    {
        $client = KositClient::fromEnvironment();
        if ($client === null) {
            self::markTestSkipped('KOSIT_ORACLE_URL is not set; run docker/kosit/run-release-gate.sh.');
        }

        $documents = ReleaseInvoices::documents();
        self::assertSame(self::DOCUMENT_KEYS, array_keys($documents));

        if ($key === self::ZUGFERD_XRECHNUNG) {
            $pdf = self::artefact(ReleaseArtefactsTest::XRECHNUNG_PDF);
            $document = (new FacturXPdfReader)->read($pdf);
            self::assertSame(FacturXProfile::XRechnung, $document->profile);
            $xml = $document->xml;
            // The XRechnung ZUGFeRD PDF carries exactly the writer output.
            self::assertSame((new FacturXXmlWriter)->write(ReleaseInvoices::germanDomestic(), FacturXProfile::XRechnung)->xml, $xml);
            $name = ReleaseArtefactsTest::XRECHNUNG_PDF.' (embedded XML)';
            $sha256 = hash('sha256', $pdf);
        } elseif (isset(self::XML_ARTEFACTS[$key])) {
            $xml = self::artefact(self::XML_ARTEFACTS[$key]);
            self::assertSame($documents[$key], $xml, self::XML_ARTEFACTS[$key].' differs from the writer output.');
            $name = self::XML_ARTEFACTS[$key];
            $sha256 = hash('sha256', $xml);
        } else {
            $xml = $documents[$key];
            $name = $key.' (writer output)';
            $sha256 = null;
        }

        $report = $client->check($xml);
        $result = $report->accepted
            ? 'accepted (scenario: '.$report->scenario.')'
            : 'rejected (scenario: '.($report->scenario ?? 'none').'): '.implode(', ', $report->verdict);
        self::record($name, self::KOSIT, self::KOSIT_VERSION, $result, $sha256);

        self::assertTrue($report->accepted, "{$name}: {$result}");
        self::assertStringContainsString('XRechnung', (string) $report->scenario, $name);
    }

    #[DataProvider('facturxCases')]
    public function test_official_facturx_xslt_passes_own_output(string $case): void
    {
        if (! OfficialRules::available()) {
            self::markTestSkipped('The SaxonC-HE PHP extension is not loaded; run docker/oracle/run.sh.');
        }
        self::assertSame(['Minimum', 'BasicWl', 'Basic', 'En16931', 'Extended'], array_keys(self::facturxCases()));
        $profile = constant(FacturXProfile::class.'::'.$case);
        self::assertInstanceOf(FacturXProfile::class, $profile);
        $key = $profile->ruleProfile();
        self::assertIsString($key);

        $xml = (new FacturXXmlWriter)->write(ReleaseInvoices::germanDomestic(), $profile)->xml;
        $name = ReleaseInvoices::INVOICE_NUMBER." Factur-X {$key} (writer output)";
        $sha256 = null;
        if ($profile === FacturXProfile::En16931) {
            // The EN 16931 ZUGFeRD PDF carries exactly this XML.
            $pdf = self::artefact(ReleaseArtefactsTest::EN16931_PDF);
            self::assertSame($xml, (new FacturXPdfReader)->read($pdf)->xml);
            $name = ReleaseArtefactsTest::EN16931_PDF.' (embedded XML)';
            $sha256 = hash('sha256', $pdf);
        }

        $verdict = OfficialRules::verdict(OfficialRules::run(Fixtures::facturxXslt($key), $xml, true, 'fatal', 'FX-REPORT'));
        $result = $verdict === [] ? 'passed: no failed assert, no fired report' : 'failed: '.implode(', ', $verdict);
        self::record($name, self::FACTURX, self::FACTURX_VERSION.', SaxonC-HE extension '.self::saxonVersion(), $result, $sha256);

        self::assertSame([], $verdict, $name);
    }

    public function test_verapdf_accepts_release_pdfs(): void
    {
        $probe = Process::run(['docker', 'version', '--format', '{{.Server.Version}}'], 30.0);
        if ($probe['status'] !== 0) {
            self::markTestSkipped('Docker is not available; veraPDF runs in docker/verapdf/run.sh.');
        }

        $dir = self::GATE_DIR.'/verapdf';
        self::makeDir($dir);
        foreach (glob($dir.'/*') ?: [] as $stale) {
            unlink($stale);
        }
        $pdfs = [ReleaseArtefactsTest::EN16931_PDF, ReleaseArtefactsTest::XRECHNUNG_PDF];
        $hashes = [];
        foreach ($pdfs as $file) {
            $bytes = self::artefact($file);
            $hashes[$file] = hash('sha256', $bytes);
            self::put($dir.'/'.$file, $bytes);
        }

        $run = Process::run(['bash', self::VERAPDF_RUNNER, $dir], self::VERAPDF_TIMEOUT);
        if ($run['status'] !== 0) {
            throw new RuntimeException("docker/verapdf/run.sh failed with exit status {$run['status']}:\n{$run['stdout']}{$run['stderr']}");
        }
        [$version, $verdicts] = self::parseVeraPdfReport($dir.'/verapdf-report.xml');

        foreach ($pdfs as $file) {
            $verdict = $verdicts[$file] ?? self::fail("veraPDF reported nothing for {$file}.");
            $result = ($verdict['compliant'] ? 'compliant' : 'not compliant')." ({$verdict['profile']})"
                .($verdict['failed'] === [] ? '' : ': '.implode(', ', $verdict['failed']));
            self::record($file, self::VERAPDF, self::VERAPDF_VERSION.", core {$version}", $result, $hashes[$file]);
        }
        foreach ($pdfs as $file) {
            self::assertSame(self::VERAPDF_PROFILE, $verdicts[$file]['profile'], $file);
            self::assertTrue($verdicts[$file]['compliant'], "{$file}: ".implode(', ', $verdicts[$file]['failed']));
            self::assertSame([], $verdicts[$file]['failed'], $file);
        }
    }

    /**
     * The bytes of a release artefact, after checking them against its SHA256SUMS line.
     */
    private static function artefact(string $file): string
    {
        $sums = self::ARTEFACTS_DIR.'/'.ReleaseArtefactsTest::SUMS;
        if (! is_file($sums)) {
            self::fail('build/release-artefacts is missing; run the release-artefacts group first.');
        }
        $expected = null;
        foreach (file($sums, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$hash, $name] = explode('  ', $line, 2) + [1 => ''];
            if ($name === $file) {
                $expected = $hash;
            }
        }
        self::assertNotNull($expected, "{$file} has no SHA256SUMS line.");
        $bytes = self::contents(self::ARTEFACTS_DIR.'/'.$file);
        self::assertSame($expected, hash('sha256', $bytes), "{$file} does not match its SHA256SUMS line.");

        return $bytes;
    }

    /**
     * The veraPDF core version and the verdict per file name of a machine-readable report.
     *
     * @return array{string, array<string, array{compliant: bool, profile: string, failed: list<string>}>}
     */
    private static function parseVeraPdfReport(string $file): array
    {
        $document = new DOMDocument;
        if (! $document->loadXML(self::contents($file), LIBXML_NONET)) {
            throw new RuntimeException("The veraPDF report {$file} is not well-formed XML.");
        }
        $version = 'unknown';
        foreach ($document->getElementsByTagName('releaseDetails') as $release) {
            if ($release->getAttribute('id') === 'core') {
                $version = $release->getAttribute('version');
            }
        }
        $verdicts = [];
        foreach ($document->getElementsByTagName('job') as $job) {
            $name = basename(trim((string) $job->getElementsByTagName('name')->item(0)?->textContent));
            $validation = $job->getElementsByTagName('validationReport')->item(0);
            if ($validation === null) {
                throw new RuntimeException("veraPDF could not validate {$name}: ".trim($job->textContent));
            }
            $failed = [];
            foreach ($validation->getElementsByTagName('rule') as $rule) {
                if ($rule->getAttribute('status') === 'failed') {
                    $failed[] = "{$rule->getAttribute('specification')} {$rule->getAttribute('clause')}-{$rule->getAttribute('testNumber')}";
                }
            }
            sort($failed, SORT_STRING);
            $verdicts[$name] = [
                'compliant' => $validation->getAttribute('isCompliant') === 'true',
                'profile' => $validation->getAttribute('profileName'),
                'failed' => array_values(array_unique($failed)),
            ];
        }

        return [$version, $verdicts];
    }

    /**
     * Merges one verdict into summary.json, keyed by document and validator. Three processes (the
     * KoSIT run, the SaxonC container and the veraPDF run) write the file, so it is read and
     * rewritten under an exclusive lock and never truncated before the read.
     *
     * @param  string|null  $sha256  the SHA-256 of the artefact file the document was read from;
     *                               null for writer output that is not an artefact
     */
    private static function record(string $document, string $validator, string $version, string $result, ?string $sha256 = null): void
    {
        self::makeDir(self::GATE_DIR);
        $path = self::GATE_DIR.'/summary.json';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}.");
        }
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException("Cannot lock {$path}.");
            }
            $existing = stream_get_contents($handle);
            $entries = is_string($existing) && trim($existing) !== '' ? json_decode($existing, true, 512, JSON_THROW_ON_ERROR) : [];
            if (! is_array($entries)) {
                throw new RuntimeException("{$path} does not hold a JSON list.");
            }
            $merged = [];
            foreach ($entries as $entry) {
                if (! is_array($entry) || ($entry['document'] ?? null) !== $document || ($entry['validator'] ?? null) !== $validator) {
                    $merged[] = $entry;
                }
            }
            $entry = ['document' => $document, 'validator' => $validator, 'version' => $version, 'result' => $result];
            if ($sha256 !== null) {
                $entry['sha256'] = $sha256;
            }
            $merged[] = $entry;
            $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
            if (! ftruncate($handle, 0) || ! rewind($handle) || fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                throw new RuntimeException("Cannot write {$path}.");
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function saxonVersion(): string
    {
        return (string) phpversion(OfficialRules::EXTENSION);
    }

    private static function makeDir(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}.");
        }
    }

    private static function put(string $path, string $bytes): void
    {
        if (file_put_contents($path, $bytes) !== strlen($bytes)) {
            throw new RuntimeException("Cannot write {$path}.");
        }
    }

    private static function contents(string $path): string
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        return $bytes;
    }
}
