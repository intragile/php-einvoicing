<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

/**
 * Where a sent document stands on its way to the receiver.
 */
enum DeliveryState: string
{
    /** Accepted by the transport, delivery not yet completed. */
    case Pending = 'pending';
    /** Delivered to the receiver's access point. */
    case Delivered = 'delivered';
    /** Delivery failed for good; the failure tells why. */
    case Failed = 'failed';
    /** The transport cannot tell, for example for a document it does not know. */
    case Unknown = 'unknown';
}
