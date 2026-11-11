<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DOMDocument;
use LibXMLError;
use RuntimeException;

/**
 * Validates a document against a local XML schema without network access.
 */
final class Xsd
{
    /**
     * Schema violations of the document, one "line N: message" entry each; empty when valid.
     *
     * @return list<string>
     */
    public static function errors(string $xml, string $schema): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_set_external_entity_loader(
            static fn (?string $public, string $system): ?string => str_contains($system, '://') ? null : $system,
        );

        try {
            $document = new DOMDocument;
            if (! $document->loadXML($xml, LIBXML_NONET)) {
                throw new RuntimeException('The document is not well-formed XML.');
            }
            $document->schemaValidate($schema);

            return array_map(
                static fn (LibXMLError $error): string => sprintf('line %d: %s', $error->line, trim($error->message)),
                libxml_get_errors(),
            );
        } finally {
            libxml_clear_errors();
            libxml_set_external_entity_loader(null);
            libxml_use_internal_errors($previous);
        }
    }
}
