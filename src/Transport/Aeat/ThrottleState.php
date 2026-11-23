<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * What the flow control remembers about one throttle key: when the last attempt was made and how
 * long AEAT asked to wait after it.
 */
final readonly class ThrottleState
{
    public function __construct(
        /** The last send attempt; null before the first one. */
        public ?DateTimeImmutable $lastSentAt = null,
        /** Seconds to wait after the last attempt (TiempoEsperaEnvio). */
        public int $waitSeconds = FlowControl::INITIAL_WAIT_SECONDS,
    ) {
        if ($waitSeconds < 0) {
            throw new InvalidArgumentException('The wait must not be negative.');
        }
    }

    /** The earliest time the next send is allowed by the wait, or null before the first attempt. */
    public function nextSendAt(): ?DateTimeImmutable
    {
        return $this->lastSentAt?->modify('+'.$this->waitSeconds.' seconds');
    }
}
