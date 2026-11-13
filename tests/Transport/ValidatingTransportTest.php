<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\InvalidDocument;
use IntrAgile\EInvoicing\Transport\FakeTransport;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\ValidatingTransport;
use IntrAgile\EInvoicing\Validation\DocumentValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use IntrAgile\EInvoicing\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatingTransportTest extends TestCase
{
    private static ?Validator $validator = null;

    public function test_invalid_document_is_refused_before_the_inner_transport(): void
    {
        $valid = OutboundDocument::fromInvoice(self::invoice());
        $broken = str_replace(
            '<cbc:PayableAmount currencyID="EUR">2599.50</cbc:PayableAmount>',
            '<cbc:PayableAmount currencyID="EUR">2999.50</cbc:PayableAmount>',
            $valid->xml,
            $count,
        );
        self::assertSame(1, $count);
        $document = OutboundDocument::fromXml($broken);
        $inner = new FakeTransport;

        try {
            (new ValidatingTransport($inner, self::validator()))->send($document);
            self::fail('InvalidDocument was not thrown.');
        } catch (InvalidDocument $exception) {
            self::assertFalse($exception->isRetryable());
            self::assertFalse($exception->report->isValid());
            self::assertContains('BR-CO-16', array_map(static fn ($f): string => $f->ruleId, $exception->report->fatal()));
            self::assertStringContainsString('BR-CO-16', $exception->getMessage());
        }
        self::assertSame([], $inner->sent());
    }

    public function test_valid_document_is_delegated(): void
    {
        $document = OutboundDocument::fromInvoice(self::invoice());
        $inner = new FakeTransport;
        $transport = new ValidatingTransport($inner, self::validator());

        $result = $transport->send($document);

        self::assertSame('fake-1', $result->documentId);
        self::assertSame([$document], $inner->sent());
        self::assertSame(DeliveryState::Delivered, $transport->status('fake-1')->state);
    }

    public function test_accepts_any_document_validator(): void
    {
        $validator = new class implements DocumentValidator
        {
            /** @var list<string> */
            public array $seen = [];

            public function validate(string $xml): ValidationReport
            {
                $this->seen[] = $xml;

                return new ValidationReport(['recording 1.0'], Syntax::Ubl, [], []);
            }
        };
        $document = OutboundDocument::fromInvoice(self::invoice());
        $inner = new FakeTransport;

        (new ValidatingTransport($inner, $validator))->send($document);

        self::assertSame([$document->xml], $validator->seen);
        self::assertSame([$document], $inner->sent());
    }

    private static function validator(): Validator
    {
        return self::$validator ??= Validator::en16931();
    }

    private static function invoice(): Invoice
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());

        return $invoice
            ->withProcessControl(new ProcessControl('urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0', PeppolBis3::PROCESS))
            ->withSeller(self::withAddress($invoice->seller, new ElectronicAddress('0123456789', ElectronicAddressScheme::C_0208)))
            ->withBuyer(self::withAddress($invoice->buyer, new ElectronicAddress('DE123456789', ElectronicAddressScheme::C_9930)));
    }

    private static function withAddress(Party $party, ElectronicAddress $address): Party
    {
        return new Party(
            name: $party->name,
            tradingName: $party->tradingName,
            identifiers: $party->identifiers,
            legalRegistration: $party->legalRegistration,
            vatIdentifier: $party->vatIdentifier,
            taxRegistration: $party->taxRegistration,
            additionalLegalInformation: $party->additionalLegalInformation,
            electronicAddress: $address,
            address: $party->address,
            contact: $party->contact,
        );
    }
}
