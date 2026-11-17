<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\France\Treatment;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FranceTest extends TestCase
{
    public function test_billing_frameworks_match_br_fr_08(): void
    {
        $expected = ['B1', 'S1', 'M1', 'B2', 'S2', 'M2', 'S3', 'B4', 'S4', 'M4', 'S5', 'S6', 'B7', 'S7', 'B8', 'S8', 'M8', 'B9', 'S9', 'M9'];

        self::assertSame($expected, array_map(static fn (BillingFramework $f): string => $f->value, BillingFramework::cases()));
    }

    public function test_treatments_match_br_fr_20(): void
    {
        self::assertSame(
            ['B2B', 'B2BINT', 'B2C', 'B2CINT', 'OUTOFSCOPE', 'ARCHIVEONLY'],
            array_map(static fn (Treatment $t): string => $t->value, Treatment::cases()),
        );
    }

    public function test_profile_from_specification_identifier(): void
    {
        self::assertSame(FrenchProfile::En16931, FrenchProfile::fromSpecificationIdentifier('urn:cen.eu:en16931:2017'));
        self::assertSame(
            FrenchProfile::ExtendedCtcFr,
            FrenchProfile::fromSpecificationIdentifier('urn:cen.eu:en16931:2017#conformant#urn.cpro.gouv.fr:1p0:extended-ctc-fr'),
        );
        self::assertNull(FrenchProfile::fromSpecificationIdentifier('urn:other'));
    }

    public function test_facturx_extended_is_french_only_with_a_french_billing_framework(): void
    {
        $extended = FacturXProfile::Extended->guidelineId();

        self::assertNull(FrenchInvoice::fromProcessControl(new ProcessControl($extended)));
        self::assertNull(FrenchInvoice::fromProcessControl(new ProcessControl($extended, 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0')));
        self::assertSame(
            FrenchProfile::ExtendedCtcFr,
            FrenchInvoice::fromProcessControl(new ProcessControl($extended, BillingFramework::B1->value))?->profile,
        );
    }

    public function test_mark_sets_process_control_and_extension(): void
    {
        $marked = France::mark($this->invoice(), BillingFramework::S1, FrenchProfile::ExtendedCtcFr);

        self::assertSame(FrenchProfile::ExtendedCtcFr->value, $marked->processControl?->specificationIdentifier);
        self::assertSame('S1', $marked->processControl->businessProcessType);
        $extension = $marked->extensions()->get(FrenchInvoice::class);
        self::assertInstanceOf(FrenchInvoice::class, $extension);
        self::assertSame(FrenchProfile::ExtendedCtcFr, $extension->profile);
        self::assertSame(FrenchProfile::En16931, (new FrenchInvoice)->profile);
    }

    /**
     * Marking an invoice read with GROUP or INFORMATION lines changes only the profile; the
     * sub-lines and the line order stay attached, so the writer still carries them.
     */
    public function test_mark_keeps_informational_lines_and_line_order(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr();
        $before = $invoice->extensions()->get(FrenchInvoice::class);
        self::assertNotNull($before);
        self::assertNotSame([], $before->informationalLines);

        $marked = France::mark($invoice, BillingFramework::S1, FrenchProfile::ExtendedCtcFr);

        $after = $marked->extensions()->get(FrenchInvoice::class);
        self::assertNotNull($after);
        self::assertSame(FrenchProfile::ExtendedCtcFr, $after->profile);
        self::assertSame($before->informationalLines, $after->informationalLines);
        self::assertSame($before->lineOrder, $after->lineOrder);
        self::assertStringContainsString('<ram:LineID>4</ram:LineID>', (new CiiWriter)->write($marked));
    }

    public function test_mark_to_en16931_with_informational_lines_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EXT-FR-FE-163');

        France::mark(FrenchInvoices::extendedCtcFr(), BillingFramework::B1, FrenchProfile::En16931);
    }

    public function test_notes_carry_their_subject_codes(): void
    {
        self::assertSame(TextSubjectCode::PMT, France::recoveryFeeNote('x')->subjectCode);
        self::assertSame(TextSubjectCode::PMD, France::latePaymentPenaltyNote('x')->subjectCode);
        self::assertSame(TextSubjectCode::AAB, France::earlyPaymentDiscountNote('x')->subjectCode);
        self::assertSame(TextSubjectCode::TXD, France::singleVatGroupNote('x')->subjectCode);
        self::assertSame('text', France::recoveryFeeNote('text')->text);

        $bar = France::treatmentNote(Treatment::B2BINT);
        self::assertSame(TextSubjectCode::BAR, $bar->subjectCode);
        self::assertSame('B2BINT', $bar->text);
    }

    private function invoice(): Invoice
    {
        return new Invoice(
            number: 'A-1',
            issueDate: new DateTimeImmutable('2026-01-15'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::EUR,
            seller: new Party('Seller'),
            buyer: new Party('Buyer'),
            lines: [new InvoiceLine('1', 1, UnitCode::C62, new Price(100), new Item('Thing'), new LineVat(VatCategory::StandardRate, 20))],
        );
    }
}
