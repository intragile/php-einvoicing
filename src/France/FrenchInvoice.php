<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use InvalidArgumentException;

/**
 * French invoice extension, attached to an invoice through its extension bag.
 */
final readonly class FrenchInvoice
{
    /**
     * @param  list<InformationalLine>  $informationalLines  GROUP and INFORMATION lines
     *                                                       (EXT-FR-FE-163), kept outside the invoice
     *                                                       lines and the totals.
     * @param  list<string>  $lineOrder  Every line identifier (BT-126), regular and informational,
     *                                   once, in document order; required when there are
     *                                   informational lines. Empty means the invoice line order.
     *                                   FrenchGuard checks it before writing.
     *
     * @throws InvalidArgumentException when informational lines are given for another profile
     *                                  than EXTENDED-CTC-FR
     */
    public function __construct(
        /** Profile the invoice is produced for. */
        public FrenchProfile $profile = FrenchProfile::En16931,
        public array $informationalLines = [],
        public array $lineOrder = [],
    ) {
        if ($informationalLines !== [] && $profile !== FrenchProfile::ExtendedCtcFr) {
            throw new InvalidArgumentException(sprintf(
                'GROUP and INFORMATION lines (EXT-FR-FE-163) need the EXTENDED-CTC-FR profile %s.',
                FrenchProfile::ExtendedCtcFr->value,
            ));
        }
    }

    /**
     * The invoice lines and the GROUP and INFORMATION lines in the line order; without a line
     * order, the invoice lines in their order. Expects an invoice FrenchGuard has accepted, whose
     * line order lists every line identifier once.
     *
     * @return list<InvoiceLine|InformationalLine>
     */
    public function linesInDocumentOrder(Invoice $invoice): array
    {
        if ($this->lineOrder === []) {
            return $invoice->lines;
        }
        $byId = [];
        foreach ([...$invoice->lines, ...$this->informationalLines] as $line) {
            $byId[$line->id] = $line;
        }

        return array_map(static fn (string $id): InvoiceLine|InformationalLine => $byId[$id], $this->lineOrder);
    }

    /**
     * Recognises a French invoice from its process control (BG-2): the business process type
     * (BT-23) is a French billing framework, or the specification identifier (BT-24) is the
     * French extended profile. The EN 16931 identifier alone is not enough, because every CEN
     * invoice carries it. The profile is taken from BT-24; a French invoice under the Factur-X
     * EXTENDED guideline is EXTENDED-CTC-FR, the profile whose French data a French Factur-X
     * EXTENDED invoice carries.
     */
    public static function fromProcessControl(?ProcessControl $processControl): ?self
    {
        if ($processControl === null) {
            return null;
        }
        $framework = $processControl->businessProcessType === null ? null : BillingFramework::tryFrom($processControl->businessProcessType);
        $profile = $framework !== null && $processControl->specificationIdentifier === FacturXProfile::Extended->guidelineId()
            ? FrenchProfile::ExtendedCtcFr
            : FrenchProfile::fromSpecificationIdentifier($processControl->specificationIdentifier);
        if ($framework === null && $profile !== FrenchProfile::ExtendedCtcFr) {
            return null;
        }

        return new self($profile ?? FrenchProfile::En16931);
    }
}
