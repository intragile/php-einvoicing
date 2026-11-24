<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Builds the unsigned AuthTokenRequest document that is signed with XAdES and sent to
 * POST /auth/xades-signature.
 *
 * The document follows version 2.1 of the schema (resources/poland/ksef-auth-2-1), which KSeF
 * accepts alongside 2.0; the two versions differ only in the IP address patterns of the optional
 * AuthorizationPolicy, which is not produced here.
 */
final class AuthTokenRequest
{
    public const XML_NAMESPACE = 'http://ksef.mf.gov.pl/auth/token/2.1';

    /** The SubjectIdentifierTypeEnum values of the schema. */
    public const SUBJECT_TYPES = ['certificateSubject', 'certificateFingerprint'];

    private function __construct() {}

    /**
     * @param  string  $challenge  the challenge returned by POST /auth/challenge
     * @param  string  $subjectType  how KSeF identifies the signer: by the certificate subject
     *                               (NIP, PESEL or organization identifier) or by its fingerprint
     *
     * @throws InvalidArgumentException When the subject type is not one of SUBJECT_TYPES
     */
    public static function xml(string $challenge, ContextIdentifier $context, string $subjectType = 'certificateSubject'): string
    {
        if (! in_array($subjectType, self::SUBJECT_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'The subject identifier type "%s" is not one of %s.',
                $subjectType,
                implode(', ', self::SUBJECT_TYPES),
            ));
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS(self::XML_NAMESPACE, 'AuthTokenRequest');
        $document->appendChild($root);

        $root->appendChild(self::element($document, 'Challenge', $challenge));
        $contextElement = $document->createElementNS(self::XML_NAMESPACE, 'ContextIdentifier');
        $contextElement->appendChild(self::element($document, $context->xmlElement(), $context->value));
        $root->appendChild($contextElement);
        $root->appendChild(self::element($document, 'SubjectIdentifierType', $subjectType));

        return (string) $document->saveXML();
    }

    private static function element(DOMDocument $document, string $name, string $text): DOMElement
    {
        $element = $document->createElementNS(self::XML_NAMESPACE, $name);
        // A text node, so that markup characters are escaped rather than parsed.
        $element->appendChild($document->createTextNode($text));

        return $element;
    }
}
