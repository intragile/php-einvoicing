<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland;

use IntrAgile\EInvoicing\Poland\Fa3InvoiceFacts;
use IntrAgile\EInvoicing\Tests\Support\PolishSamples;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Fa3InvoiceFactsTest extends TestCase
{
    public function test_reads_seller_nip_and_issue_date(): void
    {
        $facts = Fa3InvoiceFacts::read(PolishSamples::official('invoice-template-fa-3.xml'));

        self::assertSame(PolishSamples::SELLER_NIP, $facts->sellerNip);
        self::assertSame('2025-09-01 00:00:00', $facts->issueDate->format('Y-m-d H:i:s'));
    }

    public function test_missing_seller_nip_is_refused(): void
    {
        $xml = str_replace('<NIP>'.PolishSamples::SELLER_NIP.'</NIP>', '', PolishSamples::official('invoice-template-fa-3.xml'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Podmiot1/DaneIdentyfikacyjne/NIP');

        Fa3InvoiceFacts::read($xml);
    }

    public function test_impossible_issue_date_is_refused(): void
    {
        $xml = str_replace('<P_1>2025-09-01</P_1>', '<P_1>2025-02-30</P_1>', PolishSamples::official('invoice-template-fa-3.xml'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid issue date Fa/P_1 "2025-02-30"');

        Fa3InvoiceFacts::read($xml);
    }
}
