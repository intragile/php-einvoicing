<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Latarnia;

/**
 * The answer of the Latarnia status endpoint: the current availability and the messages it
 * follows from, newest publication first. The list is empty when KSeF is fully available.
 */
final readonly class LatarniaStatus
{
    /**
     * @param  list<LatarniaMessage>  $messages
     */
    public function __construct(
        public KsefAvailability $availability,
        public array $messages = [],
    ) {}
}
