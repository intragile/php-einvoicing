<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Pdf;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\PdfDocument;
use IntrAgile\EInvoicing\Syntax\FacturX\AfRelationship;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfReader;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\InvoiceAlreadyEmbedded;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Syntax\FacturX\FacturXPdfReaderTest;
use PHPUnit\Framework\TestCase;

/**
 * The PDF parser, reader and writer over the downloaded Mustang corpus: the FeRD 2.5.2 example
 * PDFs and the other test PDFs of the Mustang library and validator. Skipped when the corpus has
 * not been downloaded (composer fetch-official -- --facturx-examples).
 */
final class MustangCorpusTest extends TestCase
{
    /** Inputs whose cross-reference index can only be rebuilt by scanning the file. */
    public const array RECONSTRUCTED = [
        'validator/corruptedV2PDF.pdf',
        'validator/invalidXMP-ParseError.pdf',
    ];

    /** The corpus PDFs outside the FeRD example folders that carry no invoice. */
    public const array WITHOUT_INVOICE = [
        'library/MustangGnuaccountingBeispielRE-20170509_505PDF14.pdf',
        'library/MustangGnuaccountingBeispielRE-20170509_505PDFA3.pdf',
        'library/MustangGnuaccountingBeispielRE-20170509_505blanko.pdf',
        'library/MustangGnuaccountingBeispielRE-20171118_506blanko.pdf',
        'library/MustangGnuaccountingBeispielRE-20190610_507blanko.pdf',
        'library/MustangGnuaccountingBeispielRE-20201121_508blanko.pdf',
        'library/MustangGnuaccountingBeispielRE-20201121_508blankoA3.pdf',
        'library/ORDER-X_EX01_ORDER_FULL_DATA-COMFORT.pdf',
        'library/veraPDFtestsuite6-7-11-t01-fail-a.pdf',
        'validator/ORDER-X_EX01_ORDER_FULL_DATA-COMFORT.pdf',
    ];

    public function test_every_pdf_parses(): void
    {
        $parsed = 0;
        $reconstructed = [];
        foreach (Fixtures::facturxExamplePdfs() as $name => $path) {
            $document = PdfDocument::parse(self::contents($path));
            $parsed++;
            if ($document->reconstructed()) {
                $reconstructed[] = $name;
            }
        }

        self::assertSame(102, $parsed);
        self::assertSame(self::RECONSTRUCTED, $reconstructed);
    }

    public function test_every_ferd_invoice_reads(): void
    {
        $read = 0;
        foreach (Fixtures::facturxExamplePdfs() as $name => $path) {
            if (! str_contains($name, '/ZF252_Beispiele/') || in_array($name, FacturXPdfReaderTest::FERD_EXCLUDED, true)) {
                continue;
            }
            self::assertNotSame('', (new FacturXPdfReader)->read(self::contents($path))->xml, $name);
            $read++;
        }

        self::assertSame(61, $read);
    }

    /**
     * Every corpus PDF without an invoice takes a writer-generated EN 16931 invoice, and the reader
     * returns exactly that XML from the output. The writer refuses the PDFs that already carry an
     * invoice and those whose cross-reference index had to be reconstructed.
     */
    public function test_embed_into_every_pdf_without_an_invoice(): void
    {
        $xml = (new FacturXXmlWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()), FacturXProfile::En16931)->xml;
        $writer = new FacturXPdfWriter;
        $embedded = [];
        $refused = 0;
        foreach (Fixtures::facturxExamplePdfs() as $name => $path) {
            if (in_array($name, FacturXPdfReaderTest::FERD_EXCLUDED, true)) {
                continue;
            }
            $pdf = self::contents($path);
            if (in_array($name, self::RECONSTRUCTED, true)) {
                try {
                    $writer->embed($pdf, $xml, modified: new DateTimeImmutable('2026-01-01T00:00:00Z'));
                    self::fail($name.' was embedded into although its index was reconstructed.');
                } catch (UnreadablePdf $e) {
                    self::assertStringContainsString('reconstructed', $e->getMessage(), $name);
                }

                continue;
            }
            try {
                $result = $writer->embed($pdf, $xml, modified: new DateTimeImmutable('2026-01-01T00:00:00Z'));
            } catch (InvoiceAlreadyEmbedded) {
                $refused++;

                continue;
            }
            $embedded[] = $name;

            $output = PdfDocument::parse($result->pdf);
            self::assertFalse($output->reconstructed(), $name);
            self::assertTrue(str_starts_with($result->pdf, $pdf), $name.': the update must keep the original bytes.');
            $document = (new FacturXPdfReader)->read($result->pdf);
            self::assertSame($xml, $document->xml, $name);
            self::assertSame(FacturXProfile::En16931, $document->profile, $name);
            self::assertSame(AfRelationship::Data, $document->relationship, $name);
            self::assertSame(3, $document->pdfaPart, $name);
        }

        self::assertSame(self::WITHOUT_INVOICE, $embedded);
        self::assertSame(102 - count(FacturXPdfReaderTest::FERD_EXCLUDED) - count(self::RECONSTRUCTED) - count(self::WITHOUT_INVOICE), $refused);
    }

    /**
     * The reader refuses every PDF the writer could embed into, for the reason that it carries no
     * invoice yet.
     */
    public function test_reader_finds_no_invoice_in_the_inputs(): void
    {
        $pdfs = Fixtures::facturxExamplePdfs();
        foreach (self::WITHOUT_INVOICE as $name) {
            try {
                (new FacturXPdfReader)->read(self::contents($pdfs[$name]));
                self::fail($name.' carries an invoice.');
            } catch (UnsupportedDocument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private static function contents(string $path): string
    {
        $bytes = file_get_contents($path);
        self::assertIsString($bytes, $path);

        return $bytes;
    }
}
