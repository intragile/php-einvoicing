<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

/**
 * Why a delivery failed. The values are the snake_case category names used by access point APIs.
 */
enum FailureCategory: string
{
    /** The receiver is not registered on the network. */
    case RecipientNotFound = 'recipient_not_found';
    /** The receiver is registered but does not accept this document type or process. */
    case DocumentNotSupported = 'document_not_supported';
    /** The document failed validation. */
    case Validation = 'validation';
    /** The network transfer to the receiver's access point failed. */
    case Transport = 'transport';
    /** The receiver's access point refused the document. */
    case RecipientRejected = 'recipient_rejected';
    /** The document was sent before. */
    case Duplicate = 'duplicate';
    /** Any other reason, including categories this version does not know. */
    case Other = 'other';

    /**
     * The category of a provider value; a value this version does not know is Other.
     */
    public static function fromValue(string $value): self
    {
        return self::tryFrom($value) ?? self::Other;
    }
}
