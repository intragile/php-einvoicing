<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

/**
 * One XML Schema violation reported by libxml for a validated document.
 */
final readonly class SchemaError
{
    /**
     * @param  int  $line  line of the offending node in the source document, 0 when unknown
     * @param  int  $column  column reported by libxml, 0 when unknown
     * @param  string  $message  libxml's message, trimmed to a single line
     * @param  bool  $fatal  true when libxml reported a fatal error rather than a validity error
     */
    public function __construct(
        public int $line,
        public int $column,
        public string $message,
        public bool $fatal,
    ) {}
}
