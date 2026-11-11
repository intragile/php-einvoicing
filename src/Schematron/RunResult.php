<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

/**
 * Outcome of running a rule set over one document.
 */
final readonly class RunResult
{
    /**
     * @param  list<Finding>  $findings  in pattern order, then document order, then assert order
     * @param  list<DynamicError>  $errors
     * @param  bool  $rootRuleFired  whether a rule whose context names the document element (such as
     *                               "/ubl:Invoice") handled it; false means the document is of another syntax
     */
    public function __construct(
        public array $findings,
        public array $errors,
        public bool $rootRuleFired,
    ) {}
}
