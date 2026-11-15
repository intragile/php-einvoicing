<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Tests\Transport\B2Brouter\B2BrouterLiveTest;
use IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe\EInvoiceBeLiveTest;
use IntrAgile\EInvoicing\Tests\Transport\Recommand\RecommandLiveTest;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\InstalledRules;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeConfig;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\InboundDocument;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\InboundSource;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandConfig;
use IntrAgile\EInvoicing\Validation\PeppolPayloadValidator;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\SkippedTest;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

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
        // Each redactor comes from the factory the live test's setUp() uses, so a live test that
        // stops redacting one of its ids fails here.
        $recommand = new RecommandConfig('FAKEKEYID0000000', 'FAKESECRET000000', 'c_FAKECOMPANY000');
        $receiver = new RecommandConfig('FAKEKEYID0000000', 'FAKESECRET000000', 'c_FAKERECEIVER00');
        $recommandRedactor = RecommandLiveTest::redactorFor($recommand, $receiver);
        yield 'recommand api key id' => [$recommandRedactor, 'FAKEKEYID0000000'];
        yield 'recommand secret' => [$recommandRedactor, 'FAKESECRET000000'];
        yield 'recommand basic pair' => [$recommandRedactor, base64_encode('FAKEKEYID0000000:FAKESECRET000000')];
        yield 'recommand company id' => [$recommandRedactor, 'c_FAKECOMPANY000'];
        yield 'recommand receiving company id' => [$recommandRedactor, 'c_FAKERECEIVER00'];

        $b2brouterRedactor = B2BrouterLiveTest::redactorFor(new B2BrouterConfig('test_FAKEKEY000000', 78234123), 'HU12345676');
        yield 'b2brouter api key' => [$b2brouterRedactor, 'test_FAKEKEY000000'];
        yield 'b2brouter account id' => [$b2brouterRedactor, '78234123'];
        yield 'b2brouter account tax identifier' => [$b2brouterRedactor, 'HU12345676'];

        $company = ParticipantId::parse('0208:0000000097');
        $einvoicebeRedactor = EInvoiceBeLiveTest::redactorFor(new EInvoiceBeConfig('FAKEEINVOICEKEY0000'), $company);
        yield 'einvoicebe api key' => [$einvoicebeRedactor, 'FAKEEINVOICEKEY0000'];
        yield 'einvoicebe company peppol id' => [$einvoicebeRedactor, '0208:0000000097'];
        yield 'einvoicebe company peppol id value' => [$einvoicebeRedactor, '0000000097'];
        yield 'einvoicebe api key before the company is known' => [EInvoiceBeLiveTest::redactorFor(new EInvoiceBeConfig('FAKEEINVOICEKEY0000')), 'FAKEEINVOICEKEY0000'];
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

    public function test_with_party_company_keeps_bis3_valid(): void
    {
        $xml = LiveTransport::withPartyCompany(
            LiveTransport::withPartyCompany(
                LiveTransport::baseExample('LIVE-0123456789ab', ParticipantId::parse('0088:9508397101047'), ParticipantId::parse('9910:HU12345676')),
                'AccountingSupplierParty',
                'HU12345676',
                'hu',
            ),
            'AccountingCustomerParty',
            'SE556000000101',
            'se',
        );

        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        self::assertSame('HU12345676', $xpath->evaluate('string(//*[local-name()="AccountingSupplierParty"]//*[local-name()="PartyTaxScheme"]/*[local-name()="CompanyID"])'));
        self::assertSame('HU', $xpath->evaluate('string(//*[local-name()="AccountingSupplierParty"]//*[local-name()="PostalAddress"]//*[local-name()="IdentificationCode"])'));
        self::assertSame('SE', $xpath->evaluate('string(//*[local-name()="AccountingCustomerParty"]//*[local-name()="PostalAddress"]//*[local-name()="IdentificationCode"])'));
        self::assertSame('SE556000000101', $xpath->evaluate('string(//*[local-name()="AccountingCustomerParty"]//*[local-name()="PartyTaxScheme"]/*[local-name()="CompanyID"])'));
        $legal = '//*[local-name()="AccountingCustomerParty"]//*[local-name()="PartyLegalEntity"]/*[local-name()="CompanyID"]';
        self::assertSame('SE556000000101', $xpath->evaluate('string('.$legal.')'));
        self::assertSame(0.0, $xpath->evaluate('count('.$legal.'/@schemeID)'));
        $supplierLegal = '//*[local-name()="AccountingSupplierParty"]//*[local-name()="PartyLegalEntity"]/*[local-name()="CompanyID"]';
        self::assertSame('HU12345676', $xpath->evaluate('string('.$supplierLegal.')'));

        $report = PeppolPayloadValidator::create(InstalledRules::dir())->validate($xml);
        self::assertTrue($report->isValid(), print_r($report, true));
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

    public function test_fail_redacted_removes_secrets_from_exceptions_and_failures(): void
    {
        $redactor = new Redactor(['c_FAKECOMPANY000']);
        $bodies = [
            'exception' => static function (): void {
                throw new RuntimeException('GET https://app.recommand.eu/api/v1/companies/c_FAKECOMPANY000/identifiers failed');
            },
            'failure' => static function (): void {
                self::fail('inbox of c_FAKECOMPANY000 is empty');
            },
        ];

        foreach ($bodies as $name => $body) {
            $failure = self::caught(static fn () => LiveTransport::failRedacted($redactor, $body));
            self::assertInstanceOf(AssertionFailedError::class, $failure, $name);
            self::assertStringNotContainsString('c_FAKECOMPANY000', $failure->getMessage(), $name);
            self::assertStringContainsString(Redactor::REDACTED, $failure->getMessage(), $name);
        }
    }

    public function test_fail_redacted_passes_a_skip_through(): void
    {
        $skip = self::caught(static fn () => LiveTransport::failRedacted(new Redactor(['c_FAKECOMPANY000']), static function (): void {
            self::markTestSkipped('not configured');
        }));

        self::assertInstanceOf(SkippedTest::class, $skip);
    }

    public function test_await_inbound_skips_foreign_entries_that_cannot_be_read(): void
    {
        $sender = ParticipantId::parse('9930:DE000000097');
        $ours = LiveTransport::xrechnung(Syntax::Ubl, 'LIVE-0123456789ab', ParticipantId::parse('0208:0000000097'), $sender);
        $inbox = self::inbox([
            'broken' => new UnsupportedDocument('The document is not well-formed XML.'),
            'gone' => new UnexpectedResponse('Recommand answered HTTP 404.', 404),
            'other' => LiveTransport::xrechnung(Syntax::Ubl, 'LIVE-ffffffffffff', ParticipantId::parse('0208:0000000097'), $sender),
            'ours' => $ours,
        ], $sender);

        self::assertSame('ours', LiveTransport::awaitInbound($inbox, 'sent-id', $sender, 'LIVE-0123456789ab', 1, 0));
    }

    public function test_await_inbound_returns_the_sent_id_without_fetching(): void
    {
        $sender = ParticipantId::parse('9930:DE000000097');
        $inbox = self::inbox(['sent-id' => new UnsupportedDocument('never fetched')], $sender);

        self::assertSame('sent-id', LiveTransport::awaitInbound($inbox, 'sent-id', $sender, 'LIVE-0123456789ab', 1, 0));
    }

    public function test_await_inbound_gives_up_after_its_attempts_and_fails_on_unavailable_source(): void
    {
        $sender = ParticipantId::parse('9930:DE000000097');
        $other = LiveTransport::xrechnung(Syntax::Ubl, 'LIVE-ffffffffffff', ParticipantId::parse('0208:0000000097'), $sender);

        self::assertNull(LiveTransport::awaitInbound(self::inbox(['other' => $other], $sender), 'sent-id', $sender, 'LIVE-0123456789ab', 2, 0));

        $unavailable = self::inbox(['flaky' => new TransportUnavailable('Recommand did not answer.')], $sender);
        $this->expectException(TransportUnavailable::class);
        LiveTransport::awaitInbound($unavailable, 'sent-id', $sender, 'LIVE-0123456789ab', 1, 0);
    }

    /**
     * An inbox listing the given entries from the sender; fetch() answers with the XML or throws.
     *
     * @param  array<string, string|Throwable>  $entries  document id => XML or exception
     */
    private static function inbox(array $entries, ParticipantId $sender): InboundSource
    {
        return new class($entries, $sender) implements InboundSource
        {
            /** @param array<string, string|Throwable> $entries */
            public function __construct(private readonly array $entries, private readonly ParticipantId $sender) {}

            public function pending(): array
            {
                return array_map(
                    fn (string $id): InboundReference => new InboundReference($id, $this->sender, null, null, new DateTimeImmutable),
                    array_map(strval(...), array_keys($this->entries)),
                );
            }

            public function fetch(string $documentId): InboundDocument
            {
                $entry = $this->entries[$documentId];
                if ($entry instanceof Throwable) {
                    throw $entry;
                }

                return new InboundDocument(new InboundReference($documentId, $this->sender, null, null, new DateTimeImmutable), $entry);
            }

            public function acknowledge(string $documentId): void {}
        };
    }

    private static function caught(callable $run): ?Throwable
    {
        try {
            $run();
        } catch (Throwable $caught) {
            return $caught;
        }

        return null;
    }

    private function written(): string
    {
        self::assertIsResource($this->stream);
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }
}
