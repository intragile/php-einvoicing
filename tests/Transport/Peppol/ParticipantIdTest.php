<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Peppol;

use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParticipantIdTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function spellings(): array
    {
        return [
            'URI form' => ['iso6523-actorid-upis::0208:0123456789', '0208:0123456789'],
            'bare form' => ['0208:0123456789', '0208:0123456789'],
            'upper-case scheme prefix' => ['ISO6523-ACTORID-UPIS::0208:0123456789', '0208:0123456789'],
            'surrounding whitespace' => [' 0208:ABC ', '0208:ABC'],
            'lower-case value' => ['0208:abc', '0208:abc'],
        ];
    }

    #[DataProvider('spellings')]
    public function test_parses_every_spelling(string $spelling, string $expected): void
    {
        $id = ParticipantId::parse($spelling);

        self::assertSame($expected, $id->toString());
        self::assertSame('0208', $id->icd);
        self::assertSame('iso6523-actorid-upis::'.$expected, $id->toUri());
        self::assertSame(mb_strtolower($expected), mb_strtolower(ParticipantId::parse($spelling)->toString()));
    }

    public function test_equality_ignores_case_but_value_is_preserved(): void
    {
        $upper = ParticipantId::parse(' 0208:ABC ');
        $lower = ParticipantId::parse('0208:abc');

        self::assertTrue($upper->equals($lower));
        self::assertTrue($lower->equals($upper));
        self::assertSame('ABC', $upper->value);
        self::assertSame('abc', $lower->value);
        self::assertFalse($upper->equals(new ParticipantId('0088', 'ABC')));
        self::assertFalse($upper->equals(new ParticipantId('0208', 'ABD')));
    }

    public function test_from_electronic_address_keeps_the_value_as_written(): void
    {
        $id = ParticipantId::fromElectronicAddress(new ElectronicAddress('BE0123456789', ElectronicAddressScheme::C_0208));

        self::assertSame('0208:BE0123456789', $id->toString());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid(): array
    {
        return [
            'three-digit ICD' => ['208:0123456789'],
            'alphabetic ICD' => ['abcd:0123456789'],
            'empty value' => ['0208:'],
            'value with a space' => ['0208:0123 456789'],
            'value with a control character' => ["0208:0123\x01456789"],
            'no separator' => ['02080123456789'],
            'empty string' => ['  '],
            'prefix only' => ['iso6523-actorid-upis::'],
            'ICD with a trailing newline' => ["0208\n:abc"],
        ];
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid_ids(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);

        ParticipantId::parse($id);
    }

    public function test_invalid_utf8_value_has_its_own_message(): void
    {
        foreach ([fn () => new ParticipantId('0208', "abc\xC3\x28"), fn () => ParticipantId::parse("0208:abc\xFF")] as $build) {
            try {
                $build();
                self::fail('InvalidArgumentException expected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('The participant identifier value is not valid UTF-8.', $exception->getMessage());
            }
        }
    }

    public function test_constructor_validates_too(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParticipantId('208', '0123456789');
    }

    public function test_constructor_rejects_an_icd_with_a_trailing_newline(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParticipantId("0208\n", 'abc');
    }
}
