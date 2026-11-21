<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Session;

/**
 * The schema of the invoices sent in a KSeF session (FormCode in the KSeF API): system code,
 * schema version and form value.
 */
final readonly class FormCode
{
    private function __construct(
        public string $systemCode,
        public string $schemaVersion,
        public string $value,
    ) {}

    /**
     * The FA(3) structured invoice, schema version 1-0E.
     */
    public static function fa3(): self
    {
        return new self('FA (3)', '1-0E', 'FA');
    }

    /**
     * The FormCode object of a session-opening request.
     *
     * @return array{systemCode: string, schemaVersion: string, value: string}
     */
    public function json(): array
    {
        return ['systemCode' => $this->systemCode, 'schemaVersion' => $this->schemaVersion, 'value' => $this->value];
    }
}
