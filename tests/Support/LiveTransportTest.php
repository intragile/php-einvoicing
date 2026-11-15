<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\InstalledRules;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeConfig;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandConfig;
use IntrAgile\EInvoicing\Validation\PeppolPayloadValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LiveTransportTest extends TestCase
{
    /** @var resource|null */
    private $stream;

    protected function setUp(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->stream = $stream;
        LiveTransport::$stream = $stream;
    }

    protected function tearDown(): void
    {
        LiveTransport::$stream = null;
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * Every secret a live test configures, with the redactor that test passes to report().
     *
     * @return iterable<string, array{Redactor, string}>
     */
    public static function configuredSecrets(): iterable
    {
        $recommand = new RecommandConfig('FAKEKEYID0000000', 'FAKESECRET000000', 'c_FAKECOMPANY000');
        $recommandRedactor = $recommand->redactor()->with(['c_FAKECOMPANY000']);
        yield 'recommand api key id' => [$recommandRedactor, 'FAKEKEYID0000000'];
        yield 'recommand secret' => [$recommandRedactor, 'FAKESECRET000000'];
        yield 'recommand basic pair' => [$recommandRedactor, base64_encode('FAKEKEYID0000000:FAKESECRET000000')];
        yield 'recommand company id' => [$recommandRedactor, 'c_FAKECOMPANY000'];

        $b2brouter = new B2BrouterConfig('test_FAKEKEY000000', 78234123);
        $b2brouterRedactor = new Redactor([...$b2brouter->secrets(), '78234123']);
        yield 'b2brouter api key' => [$b2brouterRedactor, 'test_FAKEKEY000000'];
        yield 'b2brouter account id' => [$b2brouterRedactor, '78234123'];

        $einvoicebe = new EInvoiceBeConfig('FAKEEINVOICEKEY0000');
        $einvoicebeRedactor = $einvoicebe->redactor()->with(['tenant-FAKE00000']);
        yield 'einvoicebe api key' => [$einvoicebeRedactor, 'FAKEEINVOICEKEY0000'];
        yield 'einvoicebe tenant id' => [$einvoicebeRedactor, 'tenant-FAKE00000'];
    }

    #[DataProvider('configuredSecrets')]
    public function test_report_lines_carry_no_configured_secret(Redactor $redactor, string $secret): void
    {
        LiveTransport::report('some-live', 'answer for '.$secret.' was 200', $redactor);

        $written = $this->written();
        self::assertStringNotContainsString($secret, $written);
        self::assertSame(PHP_EOL.'[some-live] answer for '.Redactor::REDACTED.' was 200'.PHP_EOL, $written);
    }

    public function test_report_without_redactor_writes_the_line_unchanged(): void
    {
        LiveTransport::report('some-live', 'state=Delivered messageId=none');

        self::assertSame(PHP_EOL.'[some-live] state=Delivered messageId=none'.PHP_EOL, $this->written());
    }

    /**
     * @return iterable<string, array{Syntax, string}>
     */
    public static function xrechnungSyntaxes(): iterable
    {
        yield 'ubl' => [Syntax::Ubl, XRechnungPeppol::UBL_INVOICE];
        yield 'cii' => [Syntax::Cii, XRechnungPeppol::CII_INVOICE];
    }

    #[DataProvider('xrechnungSyntaxes')]
    public function test_xrechnung_routes_between_the_given_participants(Syntax $syntax, string $documentType): void
    {
        $buyer = ParticipantId::parse('0208:0000000097');
        $seller = ParticipantId::parse('9930:DE000000097');

        $xml = LiveTransport::xrechnung($syntax, 'LIVE-0123456789ab', $buyer, $seller);
        $document = OutboundDocument::fromXml($xml);

        self::assertTrue($document->receiver->equals($buyer));
        self::assertTrue($document->sender->equals($seller));
        self::assertSame($documentType, $document->documentType->value);
        self::assertSame('LIVE-0123456789ab', LiveTransport::invoiceNumberOf($xml));
    }

    public function test_bis3_routes_between_the_given_participants(): void
    {
        $buyer = ParticipantId::parse('0208:0000000097');
        $seller = ParticipantId::parse('9930:DE000000097');

        $xml = LiveTransport::bis3('LIVE-0123456789ab', $buyer, $seller);
        $document = OutboundDocument::fromXml($xml);

        self::assertTrue($document->receiver->equals($buyer));
        self::assertTrue($document->sender->equals($seller));
        self::assertSame(PeppolBis3::INVOICE, $document->documentType->value);
        self::assertSame('LIVE-0123456789ab', LiveTransport::invoiceNumberOf($xml));
    }

    public function test_live_documents_pass_the_peppol_payload_validator(): void
    {
        $buyer = ParticipantId::parse('0208:0000000097');
        $seller = ParticipantId::parse('9930:DE000000097');
        $validator = PeppolPayloadValidator::create(InstalledRules::dir());

        foreach ([
            'bis3-ubl' => LiveTransport::bis3('LIVE-0123456789ab', $buyer, $seller),
            'xrechnung-ubl' => LiveTransport::xrechnung(Syntax::Ubl, 'LIVE-0123456789ab', $buyer, $seller),
            'xrechnung-cii' => LiveTransport::xrechnung(Syntax::Cii, 'LIVE-0123456789ab', $buyer, $seller),
        ] as $case => $xml) {
            $report = $validator->validate($xml);
            self::assertTrue($report->isValid(), $case.': '.print_r($report, true));
        }
    }

    public function test_invoice_number_of_ignores_nested_ids(): void
    {
        $dom = new DOMDocument;
        $dom->loadXML(LiveTransport::xrechnung(Syntax::Cii, 'LIVE-aaaaaaaaaaaa', ParticipantId::parse('0208:0000000097'), ParticipantId::parse('9930:DE000000097')));
        $ids = (new DOMXPath($dom))->query('//*[local-name()="ID"]');

        self::assertNotFalse($ids);
        self::assertGreaterThan(1, $ids->length);
        self::assertSame('LIVE-aaaaaaaaaaaa', LiveTransport::invoiceNumberOf((string) $dom->saveXML()));
        self::assertNull(LiveTransport::invoiceNumberOf('<Other/>'));
    }

    private function written(): string
    {
        self::assertIsResource($this->stream);
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }
}
