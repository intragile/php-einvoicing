<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Directory;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;

/**
 * A participant as listed in the Peppol Directory, its business card data merged over all of its
 * entities.
 */
final readonly class DirectoryEntry
{
    /**
     * @param  list<string>  $names  every distinct entity name, in listed order
     * @param  list<string>  $documentTypes  document type identifiers with scheme, such as
     *                                       "busdox-docid-qns::urn:oasis:…::Invoice##…::2.1"
     * @param  list<array{scheme: string, value: string}>  $identifiers  additional identifiers of
     *                                                                   all entities
     */
    public function __construct(
        public ParticipantId $participant,
        public array $names,
        /** ISO 3166-1 alpha-2 country code of the first entity that states one. */
        public ?string $country,
        public array $documentTypes,
        public array $identifiers,
        /** The earliest registration date stated by an entity. */
        public ?DateTimeImmutable $registeredOn,
    ) {}
}
