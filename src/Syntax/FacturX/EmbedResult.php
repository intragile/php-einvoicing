<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

/**
 * The PDF with the embedded invoice, what was embedded, and what the caller should know about it.
 */
final readonly class EmbedResult
{
    /**
     * @param  list<string>  $warnings  conditions that did not stop the embedding, such as an
     *                                  input that declares no PDF/A conformance or is signed
     */
    public function __construct(
        public string $pdf,
        public FacturXProfile $profile,
        public AfRelationship $relationship,
        public array $warnings,
    ) {}
}
