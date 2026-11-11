<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use Brick\Math\BigDecimal;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Checks documents against the official CEN EN 16931 validation artefacts (1.3.17) executed by SaxonC-HE.
 */
#[Group('oracle')]
final class OracleTest extends TestCase
{
    private const PAYABLE_AMOUNT = [
        'ubl' => ['ns' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'path' => '//p:PayableAmount'],
        'cii' => ['ns' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'path' => '//p:SpecifiedTradeSettlementHeaderMonetarySummation/p:DuePayableAmount'],
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function payableAmountExamples(): array
    {
        return [
            'ubl ubl-tc434-example1.xml' => ['ubl', self::example('ubl', 'ubl-tc434-example1.xml')],
            'cii CII_example1.xml' => ['cii', self::example('cii', 'CII_example1.xml')],
        ];
    }

    #[DataProvider('payableAmountExamples')]
    public function test_detects_br_co_16(string $syntax, string $file): void
    {
        $original = self::contents($file);
        self::assertNotContains('BR-CO-16 fatal', OfficialRules::verdict(OfficialRules::run(Fixtures::officialXslt($syntax), $original)));

        $rules = OfficialRules::run(Fixtures::officialXslt($syntax), self::raisePayableAmount($syntax, $original));

        $matches = array_filter($rules, static fn (FiredRule $rule): bool => $rule->id === 'BR-CO-16');
        self::assertNotEmpty($matches, 'BR-CO-16 did not fire: '.implode('; ', OfficialRules::verdict($rules)));
        foreach ($matches as $rule) {
            self::assertSame('fatal', $rule->flag);
        }
    }

    public function test_refuses_a_document_of_the_other_syntax(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No rule context of the stylesheet matched the document element');

        OfficialRules::run(Fixtures::officialXslt('ubl'), self::contents(self::example('cii', 'CII_example1.xml')));
    }

    /**
     * Every official example read and written back in its own syntax, plus the model-built invoices
     * written in both syntaxes. The original file is carried along for the official examples.
     *
     * @return array<string, array{string, string, ?string}>
     */
    public static function writtenDocuments(): array
    {
        $cases = [];
        foreach (['ubl', 'cii'] as $syntax) {
            foreach (Fixtures::official($syntax) as $file) {
                $original = self::contents($file);
                $cases["{$syntax} ".basename($file)] = [$syntax, self::write($syntax, self::read($syntax, $original)), $original];
            }
        }
        $models = [
            'mixed VAT' => TotalsCalculator::complete(InvoiceFactory::mixedVat()),
            'not subject to VAT' => TotalsCalculator::complete(InvoiceFactory::notSubjectToVat()),
        ];
        foreach ($models as $name => $invoice) {
            foreach (['ubl', 'cii'] as $syntax) {
                $cases["{$syntax} {$name}"] = [$syntax, self::write($syntax, $invoice), null];
            }
        }

        return $cases;
    }

    /**
     * A written document must not fire any fatal rule. An official example that already fires fatal
     * rules itself is held to the same verdict instead: writing it must neither add nor remove a fatal
     * rule. None does with artefacts 1.3.17; BIS3_Invoice_negativ.XML is negative for Peppol BIS
     * rules only and passes the EN 16931 rules.
     */
    #[DataProvider('writtenDocuments')]
    public function test_every_written_document_passes(string $syntax, string $written, ?string $original): void
    {
        $xslt = Fixtures::officialXslt($syntax);
        $fatal = OfficialRules::fatal(OfficialRules::run($xslt, $written));

        $expected = $original === null ? [] : OfficialRules::fatal(OfficialRules::run($xslt, $original));

        self::assertSame(
            OfficialRules::verdict($expected),
            OfficialRules::verdict($fatal),
            "Fatal rules on the written document:\n".implode("\n", array_map(strval(...), $fatal)),
        );
    }

    private static function read(string $syntax, string $xml): Invoice
    {
        return $syntax === 'ubl' ? (new UblReader)->read($xml)->invoice : (new CiiReader)->read($xml)->invoice;
    }

    private static function write(string $syntax, Invoice $invoice): string
    {
        return $syntax === 'ubl' ? (new UblWriter)->write($invoice) : (new CiiWriter)->write($invoice);
    }

    private static function raisePayableAmount(string $syntax, string $xml): string
    {
        $document = new DOMDocument;
        $document->loadXML($xml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('p', self::PAYABLE_AMOUNT[$syntax]['ns']);
        $amount = ($xpath->query(self::PAYABLE_AMOUNT[$syntax]['path']) ?: null)?->item(0);
        if (! $amount instanceof DOMElement) {
            throw new RuntimeException("No payable amount in the {$syntax} example.");
        }
        $amount->textContent = (string) BigDecimal::of(trim($amount->textContent))->plus(1);

        return (string) $document->saveXML();
    }

    private static function example(string $syntax, string $name): string
    {
        foreach (Fixtures::official($syntax) as $file) {
            if (basename($file) === $name) {
                return $file;
            }
        }

        throw new RuntimeException("Official example {$name} not found.");
    }

    private static function contents(string $file): string
    {
        $xml = file_get_contents($file);
        if ($xml === false) {
            throw new RuntimeException("Cannot read {$file}.");
        }

        return $xml;
    }
}
