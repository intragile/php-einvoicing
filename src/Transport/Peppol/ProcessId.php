<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Peppol;

/**
 * A Peppol process identifier in the cenbii-procid-ubl scheme, such as
 * "urn:fdc:peppol.eu:2017:poacc:billing:01:1.0".
 */
final readonly class ProcessId
{
    public const SCHEME = 'cenbii-procid-ubl';

    public function __construct(
        /** Process identifier without scheme. */
        public string $value,
    ) {}
}
