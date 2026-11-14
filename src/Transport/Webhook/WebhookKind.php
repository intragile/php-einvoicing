<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Webhook;

/**
 * What a webhook notification is about, independent of the provider's event names.
 */
enum WebhookKind: string
{
    /** A document arrived in the inbox; fetch it by its id. */
    case DocumentReceived = 'document_received';

    /** The delivery of a sent document moved on; the event may carry the new state. */
    case DeliveryChanged = 'delivery_changed';

    /** Any other event, including types this version does not know. */
    case Other = 'other';
}
