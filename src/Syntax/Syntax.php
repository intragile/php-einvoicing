<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax;

use DOMDocument;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;

/**
 * The XML syntaxes of an EN 16931 invoice.
 */
enum Syntax: string
{
    case Ubl = 'ubl';
    case Cii = 'cii';

    private const UBL_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const UBL_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';

    private const CII = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    /**
     * Identifies the syntax by the expanded name of the document element.
     *
     * @throws UnsupportedDocument when the document element is neither a UBL Invoice or CreditNote
     *                             nor a CII CrossIndustryInvoice
     */
    public static function detect(DOMDocument $document): self
    {
        $root = $document->documentElement ?? throw new UnsupportedDocument('The document has no document element.');
        $uri = $root->namespaceURI ?? '';
        $local = (string) $root->localName;

        return match ([$uri, $local]) {
            [self::UBL_INVOICE, 'Invoice'], [self::UBL_CREDIT_NOTE, 'CreditNote'] => self::Ubl,
            [self::CII, 'CrossIndustryInvoice'] => self::Cii,
            default => throw new UnsupportedDocument(
                "The document element {{$uri}}{$local} is neither a UBL Invoice or CreditNote nor a CII CrossIndustryInvoice.",
            ),
        };
    }
}
