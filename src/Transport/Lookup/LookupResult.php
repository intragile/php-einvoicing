<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;

/**
 * The outcome of a participant lookup.
 */
final readonly class LookupResult
{
    public function __construct(
        public LookupStatus $status,
        /** The SMP base URL, when the SML named one. */
        public ?string $smpUrl,
        /** @var list<ServiceEndpoint> */
        public array $endpoints,
        /** Why the participant is not registered or the status is unknown. */
        public ?string $reason,
    ) {}

    /**
     * Whether an endpoint in service now accepts the document type in the process over the
     * transport profile. Identifiers are compared case-sensitively.
     */
    public function supports(DocumentTypeId $type, ProcessId $process, string $transportProfile = 'peppol-transport-as4-v2_0'): bool
    {
        $now = new DateTimeImmutable;
        foreach ($this->endpoints as $endpoint) {
            if ($endpoint->documentType === $type->value
                && $endpoint->process === $process->value
                && $endpoint->transportProfile === $transportProfile
                && $endpoint->isActiveAt($now)) {
                return true;
            }
        }

        return false;
    }
}
