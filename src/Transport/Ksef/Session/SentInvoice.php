<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Session;

/**
 * An invoice KSeF accepted for processing in a session: the references to ask for its status and
 * the SHA-256 digest of the sent (unencrypted) file.
 */
final readonly class SentInvoice
{
    /**
     * @param  string  $sessionReference  the reference number of the session the invoice was sent in
     * @param  string  $invoiceReference  the reference number KSeF gave the invoice
     * @param  string  $invoiceHash  the Base64 SHA-256 digest of the unencrypted invoice file
     */
    public function __construct(
        public string $sessionReference,
        public string $invoiceReference,
        public string $invoiceHash,
    ) {}

    /**
     * The opaque document id of the invoice: "<session reference>/<invoice reference>".
     */
    public function documentId(): string
    {
        return $this->sessionReference.'/'.$this->invoiceReference;
    }
}
