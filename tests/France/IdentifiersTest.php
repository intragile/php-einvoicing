<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\France\Siret;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IdentifiersTest extends TestCase
{
    public function test_siren_identifier_uses_scheme_0002(): void
    {
        $id = Siren::identifier('552100554');

        self::assertSame('552100554', $id->value);
        self::assertSame('0002', $id->scheme);
        self::assertTrue(Siren::isValid('552100554'));
    }

    public function test_siren_rejects_eight_or_ten_digits_and_letters(): void
    {
        foreach (['55210055', '5521005541', '55210055A', '', '55210055４'] as $bad) {
            self::assertFalse(Siren::isValid($bad), $bad);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"55210055"');
        Siren::identifier('55210055');
    }

    public function test_siret_identifier_uses_scheme_0009_and_yields_its_siren(): void
    {
        $id = Siret::identifier('55210055400013');

        self::assertSame('55210055400013', $id->value);
        self::assertSame('0009', $id->scheme);
        self::assertSame('552100554', Siret::siren('55210055400013'));
        self::assertFalse(Siret::isValid('5521005540001'));
        self::assertFalse(Siret::isValid('552100554000130'));
        self::assertFalse(Siret::isValid('5521005540001X'));
    }

    public function test_siret_siren_and_identifier_reject_invalid_values(): void
    {
        foreach ([fn () => Siret::siren('123'), fn () => Siret::identifier('123')] as $call) {
            try {
                $call();
                self::fail('Expected exception');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('"123"', $e->getMessage());
                self::assertStringContainsString('14', $e->getMessage());
            }
        }
    }
}
