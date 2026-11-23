<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Verifactu\CancellationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The official test vectors of the AEAT hash specification v0.1.2 §6 and of ejemploRegistro.xml.
 */
final class FingerprintTest extends TestCase
{
    private const VECTOR_1 = 'IDEmisorFactura=89890001K&NumSerieFactura=12345678/G33&FechaExpedicionFactura=01-01-2024&TipoFactura=F1&CuotaTotal=12.35&ImporteTotal=123.45&Huella=&FechaHoraHusoGenRegistro=2024-01-01T19:20:30+01:00';

    private const VECTOR_2 = 'IDEmisorFactura=89890001K&NumSerieFactura=12345679/G34&FechaExpedicionFactura=01-01-2024&TipoFactura=F1&CuotaTotal=12.35&ImporteTotal=123.45&Huella=3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60&FechaHoraHusoGenRegistro=2024-01-01T19:20:35+01:00';

    private const VECTOR_3 = 'IDEmisorFacturaAnulada=89890001K&NumSerieFacturaAnulada=12345679/G34&FechaExpedicionFacturaAnulada=01-01-2024&Huella=F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97&FechaHoraHusoGenRegistro=2024-01-01T19:20:40+01:00';

    private const VECTOR_4 = 'IDEmisorFactura=89890001K&NumSerieFactura=12345678-G66&FechaExpedicionFactura=03-02-2025&TipoFactura=R3&CuotaTotal=41.4&ImporteTotal=241.4&Huella=C9AF4AF1EF5EBBA700350DE3EEF12C2D355C56AC56F13DB2A25E0031BD2B7ED5&FechaHoraHusoGenRegistro=2025-02-03T14:30:00+01:00';

    private const HASH_1 = '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60';

    private const HASH_2 = 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97';

    private const HASH_3 = '177547C0D57AC74748561D054A9CEC14B4C4EA23D1BEFD6F2E69E3A388F90C68';

    private const HASH_4 = 'FF954378B64ED331A9B2366AD317D86E9DEC1716B12DD0ACCB172A6DC4C105AA';

    /** @return iterable<string, array{string, string}> */
    public static function officialVectors(): iterable
    {
        yield 'first alta (§6.1)' => [self::VECTOR_1, self::HASH_1];
        yield 'second alta (§6.2)' => [self::VECTOR_2, self::HASH_2];
        yield 'anulacion (§6.3)' => [self::VECTOR_3, self::HASH_3];
        yield 'ejemploRegistro.xml R3 alta' => [self::VECTOR_4, self::HASH_4];
    }

    #[DataProvider('officialVectors')]
    public function test_official_vectors_from_input_strings(string $input, string $expected): void
    {
        $this->assertSame($expected, Fingerprint::ofInput($input));
    }

    public function test_vector_four_with_two_decimals_differs(): void
    {
        $input = str_replace(['CuotaTotal=41.4&', 'ImporteTotal=241.4&'], ['CuotaTotal=41.40&', 'ImporteTotal=241.40&'], self::VECTOR_4);

        $this->assertNotSame(self::VECTOR_4, $input);
        $this->assertSame('7F422CFC6C1F396693406A5C4AE281E83A8A907678250A0A7E150CD54291F7EB', Fingerprint::ofInput($input));
    }

    public function test_records_rebuild_the_vector_inputs(): void
    {
        $system = VerifactuSamples::system();

        $first = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'),
            ChainLink::first(),
            $system,
            RecordTimestamp::fromLiteral('2024-01-01T19:20:30+01:00'),
        );
        $this->assertSame(self::VECTOR_1, Fingerprint::input($first));
        $this->assertSame(self::HASH_1, $first->fingerprint());
        $this->assertSame(self::HASH_1, Fingerprint::of($first));

        $second = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'),
            ChainLink::after($first->invoiceId(), $first->fingerprint()),
            $system,
            RecordTimestamp::fromLiteral('2024-01-01T19:20:35+01:00'),
        );
        $this->assertSame(self::VECTOR_2, Fingerprint::input($second));
        $this->assertSame(self::HASH_2, $second->fingerprint());

        $cancellation = CancellationRecord::create(
            CancellationDraft::of($second->invoiceId()),
            ChainLink::after($second->invoiceId(), $second->fingerprint()),
            $system,
            RecordTimestamp::fromLiteral('2024-01-01T19:20:40+01:00'),
        );
        $this->assertSame(self::VECTOR_3, Fingerprint::input($cancellation));
        $this->assertSame(self::HASH_3, $cancellation->fingerprint());
    }

    public function test_padded_values_hash_like_trimmed(): void
    {
        $record = RegistrationRecord::create(
            VerifactuSamples::vectorDraft(' 12345678/G33 ', '01-01-2024'),
            ChainLink::first(),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral(' 2024-01-01T19:20:30+01:00 '),
        );

        $this->assertSame(self::VECTOR_1, Fingerprint::input($record));
        $this->assertSame(self::HASH_1, $record->fingerprint());
    }

    public function test_input_is_hashed_as_utf8_bytes(): void
    {
        $input = 'NumSerieFactura=Ñ1';

        $this->assertSame(strtoupper(hash('sha256', "NumSerieFactura=\u{00D1}1")), Fingerprint::ofInput($input));
        $this->assertMatchesRegularExpression('/\A[0-9A-F]{64}\z/', Fingerprint::ofInput($input));
    }
}
