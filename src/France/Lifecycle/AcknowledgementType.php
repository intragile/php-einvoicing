<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

/**
 * Type of a lifecycle message (MDT-77): a platform acknowledgement in the transmission phase or a
 * processing response from a trading party.
 */
enum AcknowledgementType: string
{
    case Processing = '23';
    case Platform = '305';
}
