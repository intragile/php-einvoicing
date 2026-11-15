<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Release;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfReader;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\Process;
use IntrAgile\EInvoicing\Tests\Support\ReleaseInvoices;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Writes the release artefacts to build/release-artefacts: two ZUGFeRD PDFs printed by headless
 * Chrome from an HTML rendering of the release invoice, the XRechnung XML documents, and a
 * SHA256SUMS file in the format of `shasum -a 256`, so later steps can prove they handle the very
 * bytes written here.
 *
 * Needs Google Chrome; set CHROME_BIN to its executable. Chrome runs with its own profile and
 * temporary folder under build/release-artefacts/tmp and never touches the user's profile.
 */
#[Group('release-artefacts')]
final class ReleaseArtefactsTest extends TestCase
{
    private const CHROME_TIMEOUT = 60.0;

    /** Fixed modification date written into the PDFs by the Factur-X writer. */
    private const MODIFIED = '2026-11-13T00:00:00Z';

    public const EN16931_PDF = ReleaseInvoices::INVOICE_NUMBER.'-zugferd-en16931.pdf';

    public const XRECHNUNG_PDF = ReleaseInvoices::INVOICE_NUMBER.'-zugferd-xrechnung.pdf';

    public const UBL_XML = ReleaseInvoices::INVOICE_NUMBER.'-xrechnung-ubl.xml';

    public const CII_XML = ReleaseInvoices::INVOICE_NUMBER.'-xrechnung-cii.xml';

    public const CREDIT_NOTE_XML = ReleaseInvoices::CREDIT_NOTE_NUMBER.'-xrechnung-ubl-creditnote.xml';

    /**
     * The only warning the Factur-X writer may give for the Chrome print: Chrome declares no
     * PDF/A part, so the writer adds the declaration without converting the content. veraPDF
     * checks the result in the release gate.
     */
    private const EXPECTED_WARNINGS = [
        'The input PDF declares no PDF/A conformance (its XMP metadata has no pdfaid:part). Factur-X requires '
            .'PDF/A-3; the declaration was added, but the PDF was not converted and will not pass PDF/A validation unless '
            .'its content already conforms.',
    ];

    public const SUMS = 'SHA256SUMS';

    public function test_writes_artefacts_with_checksums(): void
    {
        $chrome = getenv('CHROME_BIN');
        if (! is_string($chrome) || $chrome === '' || ! is_file($chrome) || ! is_executable($chrome)) {
            self::markTestSkipped('Headless Chrome is needed to print the PDFs; set CHROME_BIN to the Chrome executable.');
        }

        self::writeArtefacts($chrome);

        $files = [self::EN16931_PDF, self::XRECHNUNG_PDF, self::UBL_XML, self::CII_XML, self::CREDIT_NOTE_XML];
        foreach ($files as $file) {
            $path = self::dir().'/'.$file;
            self::assertFileExists($path);
            self::assertGreaterThan(0, filesize($path), "{$file} is empty.");
        }

        $reader = new FacturXPdfReader;
        foreach ([self::EN16931_PDF => FacturXProfile::En16931, self::XRECHNUNG_PDF => FacturXProfile::XRechnung] as $file => $profile) {
            $document = $reader->read(self::contents(self::dir().'/'.$file));
            self::assertSame($profile, $document->profile, $file);
            self::assertSame(ReleaseInvoices::INVOICE_NUMBER, $document->invoice()->invoice->number, $file);
        }

        $lines = file(self::dir().'/'.self::SUMS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        $listed = [];
        foreach ($lines as $line) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}  [A-Za-z0-9._-]+$/', $line);
            [$hash, $file] = explode('  ', $line, 2);
            self::assertSame(hash_file('sha256', self::dir().'/'.$file), $hash, $file);
            $listed[] = $file;
        }
        self::assertSame($files, $listed);
    }

    private static function writeArtefacts(string $chrome): void
    {
        $tmp = self::dir().'/tmp';
        self::removeTree($tmp);
        self::makeDir($tmp);

        $invoice = ReleaseInvoices::germanDomestic();
        $printed = self::print($chrome, $invoice, $tmp);

        $artefacts = [
            self::EN16931_PDF => self::zugferd($printed, $invoice, FacturXProfile::En16931),
            self::XRECHNUNG_PDF => self::zugferd($printed, $invoice, FacturXProfile::XRechnung),
            self::UBL_XML => (new UblWriter)->write($invoice),
            self::CII_XML => (new CiiWriter)->write($invoice),
            self::CREDIT_NOTE_XML => (new UblWriter)->write(ReleaseInvoices::germanCreditNote()),
        ];

        $sums = '';
        foreach ($artefacts as $file => $bytes) {
            $path = self::dir().'/'.$file;
            self::put($path, $bytes);
            $sums .= hash_file('sha256', $path)."  {$file}\n";
        }
        self::put(self::dir().'/'.self::SUMS, $sums);

        self::removeTree($tmp);
    }

    private static function zugferd(string $pdf, Invoice $invoice, FacturXProfile $profile): string
    {
        $xml = (new FacturXXmlWriter)->write($invoice, $profile)->xml;

        $result = (new FacturXPdfWriter)->embed($pdf, $xml, $profile, modified: new DateTimeImmutable(self::MODIFIED));
        self::assertSame(self::EXPECTED_WARNINGS, $result->warnings, $profile->value);

        return $result->pdf;
    }

    private static function print(string $chrome, Invoice $invoice, string $tmp): string
    {
        $html = $tmp.'/invoice.html';
        $pdf = $tmp.'/invoice.pdf';
        self::put($html, HtmlRendering::render($invoice));

        // Chrome's own temporary files and profile stay in the temporary folder as well.
        $result = Process::run([
            'env', 'TMPDIR='.$tmp, $chrome,
            '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--no-pdf-header-footer',
            '--user-data-dir='.$tmp.'/chrome-profile',
            '--print-to-pdf='.$pdf,
            'file://'.str_replace('%2F', '/', rawurlencode($html)),
        ], self::CHROME_TIMEOUT);
        if ($result['status'] !== 0 || ! is_file($pdf)) {
            throw new RuntimeException("Headless Chrome did not print the PDF (exit status {$result['status']}):\n{$result['stdout']}{$result['stderr']}");
        }

        return self::contents($pdf);
    }

    private static function dir(): string
    {
        $dir = dirname(__DIR__, 2).'/build/release-artefacts';
        self::makeDir($dir);

        return $dir;
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

    private static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::removeTree($path.'/'.$entry);
            }
        }
        rmdir($path);
    }
}
