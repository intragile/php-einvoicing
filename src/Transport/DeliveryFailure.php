<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

/**
 * Why a document could not be delivered.
 */
final readonly class DeliveryFailure
{
    public function __construct(
        public FailureCategory $category,
        /** Human-readable explanation from the provider, when given. */
        public ?string $message = null,
        /** The provider's own error code, when given. */
        public ?string $providerCode = null,
    ) {}
}
