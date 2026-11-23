<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain;

use IntrAgile\EInvoicing\Spain\Nif;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NifTest extends TestCase
{
    public function test_valid_identifiers(): void
    {
        foreach (['89890001K', '89890002E', '12345678Z', 'X1234567L', 'B64829179', 'A58818501', 'B63276174', 'A39200019'] as $nif) {
            self::assertTrue(Nif::isValid($nif), $nif);
            self::assertSame($nif, Nif::parse($nif)->value);
            self::assertSame($nif, (string) Nif::parse($nif));
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'wrong DNI letter' => ['89890001A', 'check letter'];
        yield 'wrong CIF control' => ['B64829178', 'control'];
        yield 'unknown first letter' => ['I1234567A', 'format'];
        yield 'wrong length' => ['8989001K', 'format'];
        yield 'lower case' => ['89890001k', 'format'];
        yield 'empty' => ['', 'format'];
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_invalid_identifiers(string $nif, string $defect): void
    {
        self::assertFalse(Nif::isValid($nif));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($defect);
        Nif::parse($nif);
    }

    public function test_normalize(): void
    {
        self::assertSame('B64829179', Nif::normalize('es b-6482 9179'));
        self::assertSame('89890001K', Nif::normalize(' 89890001k '));
    }
}
