<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use DOMDocument;
use InvalidArgumentException;
use LibXMLError;

/**
 * Validates a parsed document against one XML Schema with libxml.
 *
 * Only the schema passed to validate() is used. libxml does not follow xsi:schemaLocation or
 * xsi:noNamespaceSchemaLocation hints in the instance when a schema is given explicitly, and
 * network access is disabled with LIBXML_NONET, so the schema and the files it imports or
 * includes must be available locally.
 *
 * The caller's libxml error handling mode is restored afterwards. libxml's error buffer is
 * cleared before validating, so earlier errors are not attributed to this document, and again
 * afterwards, so the validation errors do not leak to the caller. Nothing is cached between
 * calls.
 */
final class SchemaValidator
{
    /**
     * @return list<SchemaError> the violations in the order libxml reported them; empty when
     *                           the document is valid
     *
     * @throws InvalidArgumentException when the schema file does not exist or is not readable
     */
    public function validate(DOMDocument $document, string $xsdPath): array
    {
        if (! is_file($xsdPath) || ! is_readable($xsdPath)) {
            throw new InvalidArgumentException("The XML Schema {$xsdPath} does not exist or is not readable.");
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $valid = $document->schemaValidate($xsdPath, LIBXML_NONET);
            $errors = array_map(
                static fn (LibXMLError $error): SchemaError => new SchemaError(
                    $error->line,
                    $error->column,
                    trim($error->message),
                    $error->level === LIBXML_ERR_FATAL,
                ),
                array_values(array_filter(
                    libxml_get_errors(),
                    static fn (LibXMLError $error): bool => $error->level !== LIBXML_ERR_WARNING,
                )),
            );

            if (! $valid && $errors === []) {
                $errors[] = new SchemaError(0, 0, 'The document is not valid against the XML Schema.', true);
            }

            return $errors;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
