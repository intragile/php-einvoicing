<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Webhook;

use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryState;

/**
 * A verified webhook notification in provider-neutral form. Notifications carry no document
 * content: fetch the document by its id for that.
 */
final readonly class WebhookEvent
{
    public function __construct(
        public WebhookKind $kind,
        /** The provider's event name, kept as sent, for example "document.received". */
        public string $type,
        /** The provider's id of the document the event is about. */
        public string $documentId,
        /** The provider account the event belongs to (company, account or tenant id), when the notification names one. */
        public ?string $accountId,
        /**
         * The same for every retry of one delivery; use it to drop duplicates. It identifies
         * retries, not authenticity: for some providers it comes from an unsigned header (Recommand
         * X-Idempotency-Key, B2Brouter X-B2Brouter-Delivery-Id), so it is no defence against
         * replays and must not be treated as authenticated; keep final states final.
         */
        public string $deduplicationKey,
        /** The document's delivery state; null when the provider did not say, so read the status. */
        public ?DeliveryState $deliveryState,
        /** Why the delivery failed, for events that report a failure. */
        public ?DeliveryFailure $failure,
    ) {}
}
