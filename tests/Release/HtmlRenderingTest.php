<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Release;

use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\ReleaseInvoices;
use PHPUnit\Framework\TestCase;

final class HtmlRenderingTest extends TestCase
{
    public function test_shows_the_release_invoice_values(): void
    {
        $html = HtmlRendering::render(ReleaseInvoices::germanDomestic());

        foreach (['Muster Software GmbH', 'Beispiel Handel AG', 'RE-2026-0815', '2026-11-13', '2026-12-13', 'Softwareentwicklung', '19 %', '950.00', '180.50', '1130.50'] as $value) {
            self::assertStringContainsString($value, $html);
        }
    }

    public function test_names_the_preceding_invoice_of_a_credit_note(): void
    {
        $html = HtmlRendering::render(ReleaseInvoices::germanCreditNote());

        self::assertStringContainsString('<title>Credit note GS-2026-0042</title>', $html);
        self::assertStringContainsString('<th>Preceding invoice</th><td>RE-2026-0815</td>', $html);
    }

    public function test_escapes_every_value(): void
    {
        $html = HtmlRendering::render(InvoiceFactory::hostileText());

        self::assertStringNotContainsString('<b>', $html);
        self::assertStringNotContainsString('"quoted"', $html);
        self::assertStringNotContainsString("'apostrophe'", $html);
        self::assertStringContainsString('Kft. &amp; Co. &lt;b&gt;&quot;quoted&quot;&lt;/b&gt; &apos;apostrophe&apos;', $html);
    }
}
