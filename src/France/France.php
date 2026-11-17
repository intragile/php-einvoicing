<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\ProcessControl;
use InvalidArgumentException;

/**
 * Static helpers for French invoices. The caller supplies all note wording.
 */
final class France
{
    private function __construct() {}

    public static function processControl(BillingFramework $framework, FrenchProfile $profile = FrenchProfile::En16931): ProcessControl
    {
        return new ProcessControl($profile->value, $framework->value);
    }

    /**
     * Sets the process control and attaches the French invoice extension. An attached French
     * extension keeps its GROUP and INFORMATION lines and its line order; only the profile changes.
     *
     * @throws InvalidArgumentException when the invoice has GROUP or INFORMATION lines and the
     *                                  profile is not EXTENDED-CTC-FR
     */
    public static function mark(Invoice $invoice, BillingFramework $framework, FrenchProfile $profile = FrenchProfile::En16931): Invoice
    {
        $existing = $invoice->extensions()->get(FrenchInvoice::class);

        return $invoice
            ->withProcessControl(self::processControl($framework, $profile))
            ->withExtension(new FrenchInvoice($profile, $existing->informationalLines ?? [], $existing->lineOrder ?? []));
    }

    /** Fixed recovery fee note (subject code PMT). */
    public static function recoveryFeeNote(string $text): Note
    {
        return new Note($text, TextSubjectCode::PMT);
    }

    /** Late payment penalty note (subject code PMD). */
    public static function latePaymentPenaltyNote(string $text): Note
    {
        return new Note($text, TextSubjectCode::PMD);
    }

    /** Early payment discount note (subject code AAB). */
    public static function earlyPaymentDiscountNote(string $text): Note
    {
        return new Note($text, TextSubjectCode::AAB);
    }

    /** Single VAT group note (subject code TXD). */
    public static function singleVatGroupNote(string $text): Note
    {
        return new Note($text, TextSubjectCode::TXD);
    }

    /** Treatment note (subject code BAR) whose content is the treatment code. */
    public static function treatmentNote(Treatment $treatment): Note
    {
        return new Note($treatment->value, TextSubjectCode::BAR);
    }
}
