<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cdar;

use IntrAgile\EInvoicing\France\Lifecycle\LifecycleMessage;

/**
 * The outcome of reading a French lifecycle message: the message plus everything that was not
 * mapped.
 */
final readonly class LifecycleReadResult
{
    /**
     * @param  list<string>  $ignored  XPath-like paths of elements that were not mapped to the
     *                                 model, each optionally followed by the reason in parentheses,
     *                                 e.g. ".../ram:SpecifiedDocumentStatus/ram:ReasonCode (invalid code 'ZZZ')".
     */
    public function __construct(
        public LifecycleMessage $message,
        public array $ignored = [],
    ) {}
}
