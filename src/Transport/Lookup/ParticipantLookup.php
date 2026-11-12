<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;

/**
 * Tells whether a participant is reachable over Peppol: the SML names its SMP, the SMP lists its
 * document types or the endpoints for one of them.
 *
 * A failed lookup is Unknown, never NotRegistered, so that a caller does not fall back to another
 * channel because of a DNS or SMP outage: DNS failures, unusable SMP URLs, SMP 5xx answers,
 * timeouts and malformed or hostile SMP responses all give Unknown with the reason.
 */
final class ParticipantLookup
{
    public function __construct(
        private readonly SmlLocator $sml,
        private readonly SmpClient $smp,
    ) {}

    /**
     * Without a document type only the SMP ServiceGroup is read and the endpoints are empty; with
     * one, the endpoints for that document type are read.
     */
    public function lookup(ParticipantId $id, ?DocumentTypeId $type = null): LookupResult
    {
        $smpUrl = $this->sml->smpUrl($id);
        if ($smpUrl === null) {
            return new LookupResult(LookupStatus::Unknown, null, [], $this->sml->lastError());
        }
        if ($smpUrl === false) {
            return new LookupResult(LookupStatus::NotRegistered, null, [], sprintf('The SML has no SMP for %s.', $id->toUri()));
        }

        try {
            if ($type === null) {
                if ($this->smp->findDocumentTypes($smpUrl, $id) === null) {
                    return new LookupResult(LookupStatus::NotRegistered, $smpUrl, [], sprintf('The SMP at %s does not know %s.', $smpUrl, $id->toUri()));
                }

                return new LookupResult(LookupStatus::Registered, $smpUrl, [], null);
            }

            $endpoints = $this->smp->findEndpoints($smpUrl, $id, $type);
            if ($endpoints === null) {
                return new LookupResult(LookupStatus::NotRegistered, $smpUrl, [], sprintf(
                    'The SMP at %s has no service for %s and document type %s.',
                    $smpUrl,
                    $id->toUri(),
                    $type->value,
                ));
            }

            return new LookupResult(LookupStatus::Registered, $smpUrl, $endpoints, null);
        } catch (TransportException|UnsafeXml $exception) {
            return new LookupResult(LookupStatus::Unknown, $smpUrl, [], $exception->getMessage());
        }
    }
}
