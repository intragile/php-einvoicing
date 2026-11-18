<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Reader;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceSnapshot;
use IntrAgile\EInvoicing\Tests\Support\PolishInvoices;
use IntrAgile\EInvoicing\Tests\Support\PolishSamples;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Validator;
use IntrAgile\EInvoicing\Validation\SchemaError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Round trips over the official FA(3) templates, the hand-written brochure examples and every
 * Polish test invoice: the FA(3) fields survive read → write, the bytes survive write → read →
 * write, and the model survives write → read.
 */
final class Fa3RoundTripTest extends TestCase
{
    private const CLOCK = '2026-11-18T09:30:47+01:00';

    private const SOURCE = __DIR__.'/../../fixtures/poland/official/SOURCE.md';

    public function test_official_templates_match_their_recorded_hashes(): void
    {
        $source = file_get_contents(self::SOURCE);
        self::assertIsString($source);

        foreach (PolishSamples::officialNames() as $name) {
            self::assertSame(1, preg_match('/\| `'.preg_quote($name, '/').'` \| `([0-9a-f]{64})` \|/', $source, $match), $name);
            self::assertSame($match[1] ?? null, hash('sha256', PolishSamples::raw($name)), $name);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function documents(): array
    {
        $documents = [];
        foreach (PolishSamples::officialNames() as $name) {
            $documents['official/'.$name] = [PolishSamples::official($name)];
        }
        foreach (Fixtures::polandBrochure() as $name => $xml) {
            $documents['brochure/'.$name] = [$xml];
        }

        return $documents;
    }

    public function test_every_brochure_example_is_present(): void
    {
        self::assertSame(
            ['advance-eur.xml', 'correction-before-after.xml', 'margin-used-car.xml', 'rozliczenie.xml', 'settlement-roz.xml'],
            array_keys(Fixtures::polandBrochure()),
        );
    }

    #[DataProvider('documents')]
    public function test_every_fixture_is_schema_valid(string $xml): void
    {
        self::assertSchemaValid($xml);
    }

    #[DataProvider('documents')]
    public function test_read_write_is_schema_valid_and_keeps_fields(string $xml): void
    {
        $result = (new Fa3Reader)->read($xml);
        $written = self::write($result->invoice);

        self::assertSame([], $result->ignored);
        self::assertSchemaValid($written);
        self::assertSame(Fa3Fields::of($xml), Fa3Fields::of($written));
    }

    /**
     * @return array<string, array{Invoice}>
     */
    public static function factories(): array
    {
        return array_map(
            static fn (Invoice $invoice): array => [$invoice],
            PolishInvoices::all() + PolishInvoices::kinds(),
        );
    }

    #[DataProvider('factories')]
    public function test_write_read_write_is_byte_identical(Invoice $invoice): void
    {
        $first = self::write($invoice);
        $result = (new Fa3Reader)->read($first);

        self::assertSame([], $result->ignored);
        self::assertSame($first, self::write($result->invoice));
    }

    /**
     * The model terms FA(3) cannot give back as held, by factory: path => value read back, null when
     * the term is read as absent. Each follows an approved mapping decision; any other difference
     * fails the test.
     *
     * - A foreign AdresL2 is read whole as the city, so a foreign post code comes back joined to it.
     * - BT-115 of an invoice with Rozliczenie is read from DoZaplaty or DoRozliczenia, which is not
     *   the EN 16931 amount due the writer started from.
     */
    private const MODEL_DIFFERENCES = [
        'euBuyer' => ['buyer.address.city' => '10115 Berlin', 'buyer.address.postCode' => null],
        'exportBuyer' => ['buyer.address.city' => '02108 Boston', 'buyer.address.postCode' => null],
        'withSettlement' => ['totals.amountDue' => '-37'],
    ];

    #[DataProvider('factories')]
    public function test_model_survives_write_and_read(Invoice $invoice): void
    {
        $name = $this->dataName();
        self::assertIsString($name);
        $expected = InvoiceSnapshot::of($invoice);
        foreach (self::MODEL_DIFFERENCES[$name] ?? [] as $path => $value) {
            self::assertArrayHasKey($path, $expected);
            if ($value === null) {
                unset($expected[$path]);
            } else {
                $expected[$path] = $value;
            }
        }
        $read = (new Fa3Reader)->read(self::write($invoice))->invoice;

        self::assertSame($expected, InvoiceSnapshot::of($read));
    }

    public function test_brochure_values(): void
    {
        $brochure = Fixtures::polandBrochure();
        foreach ($brochure as $name => $xml) {
            $brochure[$name] = [$xml, self::write((new Fa3Reader)->read($xml)->invoice)];
        }

        foreach ($brochure['correction-before-after.xml'] as $xml) {
            $fields = Fa3Fields::of($xml);
            self::assertSame(['-330.00'], $fields['Fa/P_13_1']);
            self::assertSame(['-75.90'], $fields['Fa/P_14_1']);
            self::assertSame(['-405.90'], $fields['Fa/P_15']);
            self::assertSame(['KOR'], $fields['Fa/RodzajFaktury']);
            self::assertSame(['2026-07-14'], $fields['Fa/DaneFaKorygowanej/DataWystFaKorygowanej']);
            self::assertSame(['FV/110/07/2026'], $fields['Fa/DaneFaKorygowanej/NrFaKorygowanej']);
            self::assertSame([PolishInvoices::CORRECTED_KSEF_NUMBER], $fields['Fa/DaneFaKorygowanej/NrKSeFFaKorygowanej']);
            self::assertSame(
                [['3', '270.00', ['1']], ['2', '180.00', null], ['5', '600.00', ['1']], ['3', '360.00', null]],
                array_map(static fn (int $row): array => [
                    $fields["Fa/FaWiersz[{$row}]/P_8B"][0],
                    $fields["Fa/FaWiersz[{$row}]/P_11"][0],
                    $fields["Fa/FaWiersz[{$row}]/StanPrzed"] ?? null,
                ], [1, 2, 3, 4]),
            );
        }

        foreach ($brochure['advance-eur.xml'] as $xml) {
            $fields = Fa3Fields::of($xml);
            self::assertSame(['EUR'], $fields['Fa/KodWaluty']);
            self::assertSame(['2026-09-10', '2026-09-15', '2026-09-20'], $fields['Fa/ZaliczkaCzesciowa/P_6Z']);
            self::assertSame(['500', '2500', '1000'], $fields['Fa/ZaliczkaCzesciowa/P_15Z']);
            self::assertSame(['4.4512', '4.4724', '4.5148'], $fields['Fa/ZaliczkaCzesciowa/KursWalutyZW']);
            self::assertSame(['3252.03'], $fields['Fa/P_13_1']);
            self::assertSame(['747.97'], $fields['Fa/P_14_1']);
            self::assertSame(['3351.17'], $fields['Fa/P_14_1W']);
            self::assertSame(['4000.00'], $fields['Fa/P_15']);
            self::assertSame(['0'], $fields['count(Fa/FaWiersz)']);
        }

        foreach ($brochure['settlement-roz.xml'] as $xml) {
            $fields = Fa3Fields::of($xml);
            self::assertSame(['ROZ'], $fields['Fa/RodzajFaktury']);
            self::assertSame(['1', '1'], $fields['Fa/FakturaZaliczkowa/NrKSeFZN']);
            self::assertSame(['FZ/123/07/2025', 'FZ/133/08/2025'], $fields['Fa/FakturaZaliczkowa/NrFaZaliczkowej']);
            self::assertSame(['3690.00'], $fields['Fa/P_15']);
        }

        foreach ($brochure['margin-used-car.xml'] as $xml) {
            $fields = Fa3Fields::of($xml);
            self::assertSame(['1'], $fields['Fa/Adnotacje/PMarzy/P_PMarzy']);
            self::assertSame(['1'], $fields['Fa/Adnotacje/PMarzy/P_PMarzy_3_1']);
            self::assertSame(['50000.00'], $fields['Fa/P_13_11']);
            self::assertSame(['50000.00'], $fields['Fa/P_15']);
            self::assertArrayNotHasKey('Fa/FaWiersz[1]/P_12', $fields);
        }

        foreach ($brochure['rozliczenie.xml'] as $xml) {
            $fields = Fa3Fields::of($xml);
            self::assertSame(['246.00'], $fields['Fa/P_15']);
            self::assertSame(['17.00', '300.00'], [$fields['Fa/Rozliczenie/Obciazenia/Kwota'][0], $fields['Fa/Rozliczenie/Odliczenia/Kwota'][0]]);
            self::assertSame(['Zwrot kosztów - opłata skarbowa'], $fields['Fa/Rozliczenie/Obciazenia/Powod']);
            self::assertSame(['Rozliczenie salda klienta'], $fields['Fa/Rozliczenie/Odliczenia/Powod']);
            self::assertSame(['37.00'], $fields['Fa/Rozliczenie/DoRozliczenia']);
            self::assertArrayNotHasKey('Fa/Rozliczenie/DoZaplaty', $fields);
        }
    }

    private static function write(Invoice $invoice): string
    {
        return (new Fa3Writer(static fn (): DateTimeImmutable => new DateTimeImmutable(self::CLOCK)))->write($invoice);
    }

    private static function assertSchemaValid(string $xml): void
    {
        $report = Fa3Validator::create()->validate($xml);
        self::assertSame([], array_map(static fn (SchemaError $e): string => $e->message, $report->schemaErrors), $xml);
    }
}
