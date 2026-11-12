<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

use DateTimeImmutable;

/**
 * An endpoint an SMP publishes for a participant, document type and process. The SMP signature is
 * not verified, so the data is only as trustworthy as the TLS connection to the SMP.
 */
final readonly class ServiceEndpoint
{
    public function __construct(
        /** Document type identifier, without scheme for busdox-docid-qns. */
        public string $documentType,
        /** Process identifier, without scheme. */
        public string $process,
        /** Transport profile, such as "peppol-transport-as4-v2_0". */
        public string $transportProfile,
        /** The access point URL. */
        public string $address,
        /** The access point certificate, base64 DER without whitespace. */
        public ?string $certificate,
        public ?DateTimeImmutable $activation,
        public ?DateTimeImmutable $expiration,
        public ?string $technicalContactUrl,
    ) {}

    /**
     * Whether the endpoint is in service at the given time: activated and not expired.
     */
    public function isActiveAt(DateTimeImmutable $at): bool
    {
        return ($this->activation === null || $this->activation <= $at)
            && ($this->expiration === null || $at < $this->expiration);
    }
}
