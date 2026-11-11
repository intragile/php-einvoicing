<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\InvoiceSnapshot;
use IntrAgile\EInvoicing\Tests\Support\Xsd;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An invoice read from UBL, written as CII and read again is the same model.
 */
final class CrossSyntaxTest extends TestCase
{
    private const UBL_FIXTURES = __DIR__.'/../fixtures/ubl';

    /**
     * What the CII binding cannot carry as UBL does, as snapshot path => [from UBL, after CII].
     * Every other business term must survive unchanged.
     *
     * - CII holds the price discount (BT-147) inside the gross price, whose amount the schema
     *   requires, so a discount without a gross price (BT-148) is written with the gross price it
     *   implies: net price plus discount (1273.00 + 225.00).
     * - CII qualifies the seller tax registration (BT-32) with schemeID "FC"; the model keeps the
     *   syntax qualifier, which UBL takes from cac:TaxScheme/cbc:ID.
     * - The CII schema puts every ram:ID (BT-29 without a scheme) before the first ram:GlobalID
     *   (with a scheme); UBL keeps the identifiers in any order.
     *
     * @var array<string, array<string, array{scalar|null, scalar|null}>>
     */
    private const BINDING_DIFFERENCES = [
        'guide-example2.xml' => [
            'lines.0.price.grossPrice' => [null, '1498'],
        ],
        'ubl-tc434-example2.xml' => [
            'lines.0.price.grossPrice' => [null, '1498'],
        ],
        'ubl-tc434-example5.xml' => [
            'seller.taxRegistration.taxScheme' => ['LOC', 'FC'],
        ],
        'all-terms-invoice.xml' => [
            'seller.identifiers.0.value' => ['5790000435968', 'LOCAL-29'],
            'seller.identifiers.0.scheme' => ['0088', null],
            'seller.identifiers.1.value' => ['LOCAL-29', '5790000435968'],
            'seller.identifiers.1.scheme' => [null, '0088'],
            'seller.taxRegistration.taxScheme' => ['LOC', 'FC'],
        ],
    ];

    /**
     * @return array<string, array{Invoice}>
     */
    public static function ublInvoices(): array
    {
        $cases = [];
        foreach ([...Fixtures::official('ubl'), self::UBL_FIXTURES.'/all-terms-invoice.xml', self::UBL_FIXTURES.'/creditnote-specifics.xml'] as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            $cases[basename($file)] = [(new UblReader)->read($contents)->invoice];
        }
        $cases['mixed VAT'] = [TotalsCalculator::complete(InvoiceFactory::mixedVat())];
        $cases['not subject to VAT'] = [TotalsCalculator::complete(InvoiceFactory::notSubjectToVat())];

        return $cases;
    }

    #[DataProvider('ublInvoices')]
    public function test_ubl_to_cii_to_ubl(Invoice $fromUbl): void
    {
        $name = (string) $this->dataName();
        $xml = (new CiiWriter)->write($fromUbl);
        $read = (new CiiReader)->read($xml);

        self::assertSame([], Xsd::errors($xml, Fixtures::ciiSchema()));
        self::assertSame([], $read->ignored);

        $differences = self::BINDING_DIFFERENCES[$name] ?? [];
        if ($differences === []) {
            self::assertEquals($fromUbl, $read->invoice);
            self::assertTrue($fromUbl == $read->invoice);

            return;
        }

        $ubl = InvoiceSnapshot::of($fromUbl);
        $cii = InvoiceSnapshot::of($read->invoice);
        foreach ($differences as $path => $expected) {
            self::assertSame($expected, [$ubl[$path] ?? null, $cii[$path] ?? null], "Listed difference {$path}.");
            unset($ubl[$path], $cii[$path]);
        }
        self::assertSame($ubl, $cii);
    }
}
