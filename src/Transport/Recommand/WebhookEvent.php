<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Recommand;

use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryState;

/**
 * A verified Recommand webhook notification. Notifications carry no document content: fetch the
 * document by its id for that.
 */
final readonly class WebhookEvent
{
    public function __construct(
        /** The event type, for example "document.received"; types this version does not know are kept as sent. */
        public string $type,
        public string $documentId,
        public ?string $companyId,
        /** The X-Idempotency-Key header, the same for every retry of one delivery; use it to drop duplicates. */
        public ?string $idempotencyKey,
        /** The document's delivery state, for events that report one. */
        public ?DeliveryState $deliveryState,
        /** Why the Peppol delivery failed, for status events that report a failure. */
        public ?DeliveryFailure $failure,
    ) {}
}
