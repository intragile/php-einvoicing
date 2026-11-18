<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland;

use IntrAgile\EInvoicing\Poland\Nip;
use PHPUnit\Framework\TestCase;

final class NipTest extends TestCase
{
    public function test_valid_nips(): void
    {
        foreach (['1111111111', '2222222222', '5265877635', '1234563218'] as $nip) {
            self::assertTrue(Nip::isWellFormed($nip), $nip);
            self::assertTrue(Nip::isValid($nip), $nip);
        }
    }

    public function test_invalid_nips(): void
    {
        // Checksum mismatch, but the XSD pattern accepts it.
        self::assertTrue(Nip::isWellFormed('1234567890'));
        self::assertFalse(Nip::isValid('1234567890'));
        // Leading zero is outside the TNrNIP pattern.
        self::assertFalse(Nip::isWellFormed('0123456789'));
        self::assertFalse(Nip::isValid('0123456789'));
        self::assertFalse(Nip::isValid('8976111986'));
        self::assertFalse(Nip::isWellFormed('123456789'));
        self::assertFalse(Nip::isValid('123456789'));
        self::assertFalse(Nip::isValid('12345632181'));
        self::assertFalse(Nip::isValid(''));
    }

    public function test_remainder_ten_is_never_valid(): void
    {
        // Weighted sum of 123456789 is 230, remainder 10: no check digit can follow.
        foreach (range(0, 9) as $digit) {
            self::assertTrue(Nip::isWellFormed('123456789'.$digit));
            self::assertFalse(Nip::isValid('123456789'.$digit));
        }
    }

    public function test_normalize_strips_pl_prefix_and_separators(): void
    {
        self::assertSame('5265877635', Nip::normalize('PL 526-587-76-35'));
        self::assertSame('5265877635', Nip::normalize('pl5265877635'));
        self::assertSame('5265877635', Nip::normalize(' 526 587 76 35 '));
        self::assertTrue(Nip::isValid(Nip::normalize('PL 526-587-76-35')));
    }
}
