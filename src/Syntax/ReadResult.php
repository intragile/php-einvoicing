<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax;

use IntrAgile\EInvoicing\Model\Invoice;

/**
 * The outcome of reading a syntax document: the invoice plus everything that was not mapped.
 */
final readonly class ReadResult
{
    /**
     * @param  list<string>  $ignored  XPath-like paths of elements that were not mapped to the
     *                                 model, each optionally followed by the reason in parentheses,
     *                                 e.g. "/Invoice/cbc:Note[1] (invalid code 'ZZZ')".
     */
    public function __construct(
        public Invoice $invoice,
        public array $ignored = [],
    ) {}
}
