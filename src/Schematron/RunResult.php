<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Finding;

/**
 * Outcome of running a rule set over one document.
 *
 * @internal
 */
final readonly class RunResult
{
    /**
     * @param  list<Finding>  $findings  in pattern order, then document order, then assert order
     * @param  list<DynamicError>  $dynamicErrors
     * @param  bool  $rootRuleFired  whether a rule whose context names the document element (such as
     *                               "/ubl:Invoice") handled it; false means the document is of another syntax
     */
    public function __construct(
        public array $findings,
        public array $dynamicErrors,
        public bool $rootRuleFired,
    ) {}
}
