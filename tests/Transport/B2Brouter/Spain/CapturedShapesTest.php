<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\Spain;

use DOMElement;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordReader;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Tests\Support\B2BrouterSpainLive;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The B2Brouter Spanish sandbox responses captured once and committed redacted: the tax report
 * of an imported invoice, the report before it in the chain, and the invoice that lists it. The
 * record inside a report, its fingerprint and its QR image are kept byte for byte.
 */
#[Group('verifactu-official')]
final class CapturedShapesTest extends TestCase
{
    private const DIR = __DIR__.'/../../../fixtures/spain/b2brouter/api';

    /** Report fields kept byte for byte, which are checked decoded rather than as text. */
    private const BINARY_FIELDS = ['xml_base64', 'qr'];

    public function test_tax_report_record_recomputes_its_fingerprint(): void
    {
        $report = self::report('tax-report.json');
        $xml = self::recordXml($report);
        $root = self::root($xml);

        $record = (new RecordReader)->read($xml);

        self::assertIsString($report['fingerprint'] ?? null);
        self::assertSame($report['fingerprint'], Fingerprint::recompute($root));
        self::assertSame($report['fingerprint'], self::huella($root));
        self::assertSame($report['fingerprint'], $record->fingerprint());
    }

    public function test_tax_report_chains_to_the_previous_report(): void
    {
        $report = self::report('tax-report.json');
        $previous = self::report('tax-report-previous.json');
        $record = (new RecordReader)->read(self::recordXml($report));
        $previousRecord = (new RecordReader)->read(self::recordXml($previous));

        $link = $record->chainLink();
        $previousId = $link->previous();
        self::assertNotNull($previousId);
        self::assertTrue($previousId->equals($previousRecord->invoiceId()));
        self::assertSame($previous['fingerprint'], $link->previousFingerprint());
        // previous_id names another B2Brouter resource, not a tax report, so only its presence is checked.
        self::assertNotEmpty($report['previous_id'] ?? null);
    }

    public function test_invoice_lists_the_tax_report_id(): void
    {
        $invoice = self::unwrap(self::json('invoice.json'), 'invoice');
        $report = self::report('tax-report.json');

        $ids = $invoice['tax_report_ids'] ?? null;
        self::assertIsArray($ids);
        self::assertNotEmpty($ids);
        self::assertContains($report['id'], $ids);
        self::assertSame($report['id'], $ids === [] ? null : max($ids));
    }

    public function test_invoice_account_id_is_redacted(): void
    {
        foreach (['invoice.json', 'import-201.json'] as $file) {
            $invoice = self::unwrap(self::json($file), 'invoice');
            $account = $invoice['account'] ?? null;

            self::assertIsArray($account, $file);
            self::assertSame(999999, $account['id'] ?? null, $file);
        }
    }

    public function test_qr_is_a_png(): void
    {
        foreach (['tax-report.json', 'tax-report-previous.json'] as $file) {
            $report = self::report($file);
            self::assertIsString($report['qr'] ?? null, $file);
            $png = base64_decode($report['qr'], true);

            self::assertIsString($png, $file);
            self::assertStringStartsWith("\x89PNG", $png, $file);
        }
    }

    public function test_fixtures_hold_no_secret(): void
    {
        $key = (string) getenv(B2BrouterSpainLive::KEY_ENV);
        $accountId = (string) getenv(B2BrouterSpainLive::ACCOUNT_ENV);
        $files = array_values(array_filter(
            scandir(self::DIR) ?: [],
            static fn (string $file): bool => is_file(self::DIR.'/'.$file),
        ));
        self::assertContains('README.md', $files);

        foreach ($files as $file) {
            $text = (string) file_get_contents(self::DIR.'/'.$file);
            if (str_ends_with($file, '.json')) {
                $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
                self::assertIsArray($data, $file);
                self::assertAccountIdsRedacted($data, $file);
                $text = self::searchableText($data);
            }

            self::assertDoesNotMatchRegularExpression('/test_[A-Za-z0-9]{6,}/', $text, $file);
            self::assertStringNotContainsString('@', str_replace('redacted@example.invalid', '', $text), $file);
            if ($key !== '') {
                self::assertStringNotContainsString($key, $text, $file.' holds the API key');
            }
            if ($accountId !== '') {
                self::assertDoesNotMatchRegularExpression('/(?<![0-9])'.preg_quote($accountId, '/').'(?![0-9])/', $text, $file.' holds the account id');
            }
        }
    }

    /**
     * The JSON as text, with each Base64 field replaced by its decoded text: the record XML is
     * searched as XML, and the PNG holds pixels only, so its bytes are left out.
     *
     * @param  array<mixed>  $data
     */
    private static function searchableText(array $data): string
    {
        $decoded = [];
        array_walk_recursive($data, static function (mixed &$value, int|string $name) use (&$decoded): void {
            if (in_array($name, self::BINARY_FIELDS, true) && is_string($value)) {
                if ($name === 'xml_base64') {
                    $decoded[] = (string) base64_decode($value, true);
                }
                $value = '';
            }
        });

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).implode("\n", $decoded);
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function assertAccountIdsRedacted(array $data, string $file): void
    {
        foreach ($data as $name => $value) {
            if ($name === 'account_id') {
                self::assertSame(999999, $value, $file);
            }
            if (is_array($value)) {
                self::assertAccountIdsRedacted($value, $file);
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private static function report(string $file): array
    {
        return self::unwrap(self::json($file), 'tax_report');
    }

    /**
     * The object under the key when the response wraps it, else the response itself.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function unwrap(array $data, string $key): array
    {
        $inner = $data[$key] ?? $data;
        self::assertIsArray($inner);

        return $inner;
    }

    /**
     * @return array<mixed>
     */
    private static function json(string $file): array
    {
        $path = self::DIR.'/'.$file;
        self::assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param  array<mixed>  $report
     */
    private static function recordXml(array $report): string
    {
        self::assertIsString($report['xml_base64'] ?? null);
        $xml = base64_decode($report['xml_base64'], true);
        self::assertIsString($xml);

        return $xml;
    }

    private static function root(string $xml): DOMElement
    {
        $root = SafeXml::load($xml)->documentElement;
        self::assertInstanceOf(DOMElement::class, $root);

        return $root;
    }

    private static function huella(DOMElement $root): string
    {
        $value = null;
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === VerifactuFormat::SF_NS && $child->localName === 'Huella') {
                $value = trim($child->textContent);
            }
        }

        return (string) $value;
    }
}
