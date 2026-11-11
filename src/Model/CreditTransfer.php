<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Credit transfer (BG-17).
 */
final readonly class CreditTransfer
{
    public function __construct(
        /** Payment account identifier (BT-84), e.g. an IBAN. */
        public string $accountIdentifier,
        /** Payment account name (BT-85). */
        public ?string $accountName = null,
        /** Payment service provider identifier (BT-86), e.g. a BIC. */
        public ?string $serviceProviderIdentifier = null,
    ) {}
}
