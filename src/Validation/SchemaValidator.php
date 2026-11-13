<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use DOMDocument;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use LibXMLError;

/**
 * Validates a parsed document against one XML Schema with libxml.
 *
 * Only the schema passed to validate() is used. libxml does not follow xsi:schemaLocation or
 * xsi:noNamespaceSchemaLocation hints in the instance when a schema is given explicitly.
 * LIBXML_NONET does not apply to schema compilation, so a temporary external entity loader
 * resolves only local file paths and file:// URIs and refuses every other scheme; the schema
 * and the files it imports or includes must be available locally, and an import with another
 * scheme raises UnusableSchema naming the refused URI.
 *
 * A schema that cannot be read, parsed or compiled raises UnusableSchema, so a broken schema
 * installation can never be reported as an invalid document. libxml compiles the schema before
 * it validates the instance and stops when compilation fails, which PHP signals with an
 * "Invalid Schema" warning; that warning is captured here and turned into the exception.
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
     * @throws UnusableSchema when the schema file is missing or unreadable, or cannot be parsed
     *                        or compiled
     */
    public function validate(DOMDocument $document, string $xsdPath): array
    {
        if (! is_file($xsdPath) || ! is_readable($xsdPath)) {
            throw new UnusableSchema("The XML Schema {$xsdPath} does not exist or is not readable.");
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $phpWarning = null;
        $refused = [];
        $previousLoader = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader(static function (?string $public, string $system) use (&$refused): ?string {
            if (preg_match('~^([a-z][a-z0-9+.-]+):~i', $system, $match) === 1) {
                if (strtolower($match[1]) !== 'file') {
                    $refused[] = $system;

                    return null;
                }
                $system = rawurldecode((string) preg_replace('~^file://(localhost)?~i', '', $system));
            }

            return $system;
        });
        set_error_handler(static function (int $level, string $message) use (&$phpWarning): bool {
            $phpWarning = $message;

            return true;
        }, E_WARNING);

        try {
            try {
                $valid = $document->schemaValidate($xsdPath, LIBXML_NONET);
            } finally {
                restore_error_handler();
                libxml_set_external_entity_loader($previousLoader);
            }
            $libxmlErrors = libxml_get_errors();

            if ($refused !== []) {
                throw new UnusableSchema(
                    "The XML Schema {$xsdPath} cannot be used: network access is refused for the schema reference "
                    .implode(', ', array_unique($refused)).'.',
                );
            }

            if ($phpWarning !== null) {
                throw new UnusableSchema(self::schemaFailure($xsdPath, $phpWarning, $libxmlErrors));
            }

            $errors = array_map(
                static fn (LibXMLError $error): SchemaError => new SchemaError(
                    $error->line,
                    $error->column,
                    trim($error->message),
                    $error->level === LIBXML_ERR_FATAL,
                ),
                array_values(array_filter(
                    $libxmlErrors,
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

    /**
     * @param  array<LibXMLError>  $errors
     */
    private static function schemaFailure(string $xsdPath, string $phpWarning, array $errors): string
    {
        $details = array_map(
            static fn (LibXMLError $error): string => trim($error->message).($error->line > 0 ? " (line {$error->line})" : ''),
            $errors,
        );
        if ($details === []) {
            $details[] = $phpWarning;
        }

        return "The XML Schema {$xsdPath} cannot be used: ".implode('; ', $details);
    }
}
