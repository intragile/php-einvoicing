<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * The official FA(3) invoice templates of the Ministry of Finance KSeF C# client, committed
 * byte for byte under tests/fixtures/poland/official.
 *
 * The templates carry placeholders that the client replaces before sending. official() replaces
 * them with synthetic, checksum-valid NIPs and invoice number 1; raw() returns the file as
 * published.
 */
final class PolishSamples
{
    public const SELLER_NIP = '2222222222';

    public const THIRD_PARTY_NIP = '3333333333';

    public const INVOICE_NUMBER = '1';

    private const DIR = __DIR__.'/../fixtures/poland/official';

    private const PLACEHOLDERS = [
        '#nip#' => self::SELLER_NIP,
        '#nipOdbiorca#' => self::THIRD_PARTY_NIP,
        '#invoice_number#' => self::INVOICE_NUMBER,
    ];

    /**
     * @return list<string>
     */
    public static function officialNames(): array
    {
        return [
            'invoice-template-fa-3.xml',
            'invoice-template-fa-3-with-attachment.xml',
            'invoice-template-fa-3-with-custom-Subject3.xml',
        ];
    }

    /**
     * The template with its placeholders replaced.
     */
    public static function official(string $name): string
    {
        return strtr(self::raw($name), self::PLACEHOLDERS);
    }

    /**
     * The template exactly as published, placeholders included.
     */
    public static function raw(string $name): string
    {
        Assert::assertContains($name, self::officialNames());
        $contents = file_get_contents(self::DIR.'/'.$name);
        Assert::assertIsString($contents);

        return $contents;
    }
}
