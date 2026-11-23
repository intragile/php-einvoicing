<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\ComputerSystem;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxBreakdownLine;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxRate;
use PHPUnit\Framework\Assert;

/**
 * Synthetic VERI*FACTU inputs for tests.
 *
 * The vector draft reproduces the hashed fields of the AEAT hash specification v0.1.2 §6
 * (issuer 89890001K, F1, CuotaTotal 12.35, ImporteTotal 123.45). The computer system is a
 * fictitious producer.
 */
final class VerifactuSamples
{
    public const ISSUER_NIF = '89890001K';

    public const RECIPIENT_NIF = '89890002E';

    public static function system(): ComputerSystem
    {
        return new ComputerSystem(
            'Test Producer S.L.',
            Nif::parse('A39200019'),
            null,
            'TestSIF',
            '77',
            '1.0.03',
            '383',
            true,
            false,
            false,
        );
    }

    /** The issue date is given as AEAT writes it (dd-mm-yyyy). */
    public static function invoiceId(string $number, string $date): InvoiceId
    {
        $issueDate = DateTimeImmutable::createFromFormat('!d-m-Y', $date);
        Assert::assertInstanceOf(DateTimeImmutable::class, $issueDate);

        return new InvoiceId(Nif::parse(self::ISSUER_NIF), $number, $issueDate);
    }

    public static function vectorDraft(string $number, string $date): RegistrationDraft
    {
        return self::draft(self::invoiceId($number, $date));
    }

    /**
     * @param  list<TaxBreakdownLine>|null  $breakdown
     * @param  list<TaxParty>|null  $recipients
     */
    public static function draft(
        InvoiceId $id,
        ?array $breakdown = null,
        ?array $recipients = null,
        string $description = 'Vector invoice',
    ): RegistrationDraft {
        return new RegistrationDraft(
            id: $id,
            issuerName: 'Test Issuer S.L.',
            type: InvoiceType::F1,
            correctionType: null,
            corrected: [],
            substituted: [],
            correctionAmounts: null,
            operationDate: null,
            description: $description,
            simplifiedArt7273: false,
            withoutRecipientArt61d: false,
            macrodato: false,
            issuedBy: null,
            thirdParty: null,
            recipients: $recipients ?? [self::recipient()],
            coupon: false,
            breakdown: $breakdown ?? [self::line()],
            totalTax: Amount::of('12.35'),
            total: Amount::of('123.45'),
            externalReference: null,
            agreementNumber: null,
            systemAgreementId: null,
        );
    }

    public static function line(): TaxBreakdownLine
    {
        return new TaxBreakdownLine(
            TaxType::Iva,
            RegimeKey::Key01,
            OperationQualification::S1,
            null,
            TaxRate::of('10.00'),
            Amount::of('111.10'),
            null,
            Amount::of('12.35'),
            null,
            null,
        );
    }

    public static function recipient(): TaxParty
    {
        return new TaxParty('Test Recipient S.L.', Nif::parse(self::RECIPIENT_NIF), null);
    }
}
