<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use IntrAgile\EInvoicing\Model\ProcessControl;

/**
 * French invoice extension, attached to an invoice through its extension bag.
 */
final readonly class FrenchInvoice
{
    public function __construct(
        /** Profile the invoice is produced for. */
        public FrenchProfile $profile = FrenchProfile::En16931,
    ) {}

    /**
     * Recognises a French invoice from its process control (BG-2): the business process type
     * (BT-23) is a French billing framework, or the specification identifier (BT-24) is the
     * French extended profile. The EN 16931 identifier alone is not enough, because every CEN
     * invoice carries it. The profile is taken from BT-24.
     */
    public static function fromProcessControl(?ProcessControl $processControl): ?self
    {
        if ($processControl === null) {
            return null;
        }
        $profile = FrenchProfile::fromSpecificationIdentifier($processControl->specificationIdentifier);
        $framework = $processControl->businessProcessType === null ? null : BillingFramework::tryFrom($processControl->businessProcessType);
        if ($framework === null && $profile !== FrenchProfile::ExtendedCtcFr) {
            return null;
        }

        return new self($profile ?? FrenchProfile::En16931);
    }
}
