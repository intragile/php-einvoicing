<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use DateTimeImmutable;
use DOMDocument;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Pdf\PdfDocument;
use IntrAgile\EInvoicing\Pdf\PdfStream;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\Xmp;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Pdf\MustangCorpusTest;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * PDF/A conformance of the writer's output, checked by veraPDF (docker/verapdf/run.sh): embedding
 * an invoice into a PDF that passes PDF/A validation must give a PDF that passes too, and must
 * break no rule the input did not already fail.
 *
 * The inputs are the corpus PDFs that declare a PDF/A part and carry no invoice, plus a PDF printed
 * by headless Chrome, which declares no PDF/A part: the writer declares PDF/A-3 without converting
 * the content. Inputs, outputs and the veraPDF report are written to build/verapdf/ and never
 * committed.
 */
#[Group('verapdf')]
final class VeraPdfTest extends TestCase
{
    private const string RUNNER = __DIR__.'/../../docker/verapdf/run.sh';

    private const string WORK_DIR = __DIR__.'/../../build/verapdf';

    private const string CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

    private const string CHROME_CASE = 'chrome';

    /** Bound of the Chrome print, in seconds. */
    private const int CHROME_TIMEOUT = 120;

    /** @var array<string, array{compliant: bool, profile: string, failed: list<string>}>|null verdict per file name */
    private static ?array $report = null;

    /** @var array<string, list<string>> the writer's warnings per input slug */
    private static array $warnings = [];

    /**
     * The corpus PDFs without an invoice that declare a PDF/A part, keyed by a file-name-safe slug.
     *
     * @return array<string, array{string}>
     */
    public static function inputs(): array
    {
        // A skip raised inside a data provider is reported as an error; the test skips instead.
        if (! Fixtures::hasFacturxExamples()) {
            return ['Factur-X examples not downloaded' => ['']];
        }
        $pdfs = Fixtures::facturxExamplePdfs();
        $inputs = [];
        foreach (MustangCorpusTest::WITHOUT_INVOICE as $name) {
            $document = PdfDocument::parse(self::contents($pdfs[$name]));
            $metadata = $document->resolve($document->catalog()->get('Metadata'));
            if ($metadata instanceof PdfStream && Xmp::declaredPart($document->decodedStream($metadata)) !== null) {
                $inputs[self::slug($name)] = [$pdfs[$name]];
            }
        }

        return $inputs;
    }

    public function test_input_count(): void
    {
        Fixtures::facturxExamplesDir();
        self::assertCount(9, self::inputs());
    }

    #[DataProvider('inputs')]
    public function test_output_keeps_the_input_verdict(string $path): void
    {
        $report = self::report();
        $slug = (string) array_search([$path], self::inputs(), true);
        $input = $report["in-{$slug}.pdf"] ?? self::fail("veraPDF reported nothing for the input {$slug}.");
        $output = $report["out-{$slug}.pdf"] ?? self::fail("veraPDF reported nothing for the output {$slug}.");

        if ($input['compliant']) {
            self::assertTrue($output['compliant'], "{$slug}: the input passes {$input['profile']}, the output fails {$output['profile']}: ".implode(', ', $output['failed']));

            return;
        }
        if ($input['profile'] === $output['profile']) {
            self::assertSame([], array_values(array_diff($output['failed'], $input['failed'])), "{$slug}: rules the output breaks and the input did not");

            return;
        }
        // An input of another PDF/A part is checked against other rules; its own verdict stays a failure.
        $this->addToAssertionCount(1);
    }

    /**
     * The Chrome print declares no PDF/A part and fails veraPDF. The writer warns that it does not
     * convert the content (C7); it only adds what PDF/A-3 metadata needs (the trailer /ID, the XMP
     * packet and an sRGB output intent), so the output breaks no rule the input did not, and a page
     * whose content already conforms, like this one, passes.
     */
    public function test_chrome_print_is_not_converted(): void
    {
        $report = self::report();
        if (! isset($report['out-'.self::CHROME_CASE.'.pdf'])) {
            self::markTestSkipped('Google Chrome is not installed at '.self::CHROME.'; the headless Chrome case needs it.');
        }
        $input = $report['in-'.self::CHROME_CASE.'.pdf'];
        $output = $report['out-'.self::CHROME_CASE.'.pdf'];

        self::assertFalse($input['compliant']);
        self::assertStringContainsString('declares no PDF/A conformance', implode("\n", self::$warnings[self::CHROME_CASE] ?? []));
        self::assertSame($input['profile'], $output['profile']);
        self::assertSame([], array_values(array_diff($output['failed'], $input['failed'])), 'rules the output breaks and the input did not');
    }

    /**
     * Writes every input and its output to the work folder, runs veraPDF once over the folder and
     * returns its verdict per file.
     *
     * @return array<string, array{compliant: bool, profile: string, failed: list<string>}>
     */
    private static function report(): array
    {
        if (self::$report !== null) {
            return self::$report;
        }
        Fixtures::facturxExamplesDir();
        if (! is_file(self::RUNNER)) {
            throw new RuntimeException('The veraPDF runner '.self::RUNNER.' is missing.');
        }
        self::cleanWorkDir();

        $pdfs = [];
        foreach (self::inputs() as $slug => [$path]) {
            $pdfs[$slug] = self::contents($path);
        }
        $chrome = self::chromePdf();
        if ($chrome !== null) {
            $pdfs[self::CHROME_CASE] = $chrome;
        }

        $xml = (new FacturXXmlWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()), FacturXProfile::En16931)->xml;
        $writer = new FacturXPdfWriter;
        foreach ($pdfs as $slug => $pdf) {
            self::write("in-{$slug}.pdf", $pdf);
            $result = $writer->embed($pdf, $xml, modified: new DateTimeImmutable('2026-01-01T00:00:00Z'));
            self::$warnings[$slug] = $result->warnings;
            self::write("out-{$slug}.pdf", $result->pdf);
        }

        [$status, $output] = self::exec(['bash', self::RUNNER, self::workDir()]);
        if ($status !== 0) {
            throw new RuntimeException("docker/verapdf/run.sh failed with exit status {$status}:\n{$output}");
        }

        return self::$report = self::parseReport(self::workDir().'/verapdf-report.xml');
    }

    /**
     * @return array<string, array{compliant: bool, profile: string, failed: list<string>}>
     */
    private static function parseReport(string $file): array
    {
        $document = new DOMDocument;
        if (! $document->loadXML(self::contents($file), LIBXML_NONET)) {
            throw new RuntimeException("The veraPDF report {$file} is not well-formed XML.");
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

        return $verdicts;
    }

    /**
     * A one-page PDF printed by headless Chrome from a small HTML page, or null when Chrome is not
     * installed.
     */
    private static function chromePdf(): ?string
    {
        if (! is_file(self::CHROME)) {
            return null;
        }
        $html = self::workDir().'/chrome.html';
        $pdf = self::workDir().'/chrome-print.pdf.tmp';
        file_put_contents($html, '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Invoice 1</title></head>'
            .'<body><h1>Invoice 1</h1><p>Total due: 100.00 EUR</p></body></html>');

        // Chrome's own temporary files go to the work folder as well.
        [$status, $output] = self::exec([
            'env', 'TMPDIR='.self::workDir(),
            'timeout', (string) self::CHROME_TIMEOUT, self::CHROME,
            '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--no-pdf-header-footer',
            '--user-data-dir='.self::workDir().'/chrome-profile',
            '--print-to-pdf='.$pdf,
            'file://'.$html,
        ]);
        if ($status !== 0 || ! is_file($pdf)) {
            throw new RuntimeException("Headless Chrome did not print the PDF (exit status {$status}):\n{$output}");
        }

        return self::contents($pdf);
    }

    /**
     * @param  list<string>  $command
     * @return array{int, string} exit status and combined output
     */
    private static function exec(array $command): array
    {
        // The shell merges standard error into standard output, so one pipe carries both.
        $process = proc_open(['sh', '-c', 'exec "$@" 2>&1 </dev/null', 'sh', ...$command], [1 => ['pipe', 'w']], $pipes);
        if ($process === false) {
            throw new RuntimeException('Cannot start '.$command[0].'.');
        }
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return [proc_close($process), $output];
    }

    private static function cleanWorkDir(): void
    {
        $dir = self::WORK_DIR;
        if (is_dir($dir)) {
            exec('rm -rf '.escapeshellarg($dir), result_code: $status);
            if ($status !== 0) {
                throw new RuntimeException("Cannot remove {$dir}.");
            }
        }
        if (! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}.");
        }
    }

    private static function workDir(): string
    {
        return realpath(self::WORK_DIR) ?: throw new RuntimeException('The work folder '.self::WORK_DIR.' is missing.');
    }

    private static function write(string $name, string $bytes): void
    {
        if (file_put_contents(self::workDir().'/'.$name, $bytes) === false) {
            throw new RuntimeException("Cannot write {$name}.");
        }
    }

    private static function slug(string $name): string
    {
        return (string) preg_replace('/[^A-Za-z0-9]+/', '_', preg_replace('/\.pdf$/i', '', $name) ?? $name);
    }

    private static function contents(string $path): string
    {
        return file_get_contents($path) ?: throw new RuntimeException("Cannot read {$path}.");
    }
}
