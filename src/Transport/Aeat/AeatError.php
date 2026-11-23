<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use IntrAgile\EInvoicing\Transport\FailureCategory;

/**
 * The classification of one AEAT error code.
 */
final readonly class AeatError
{
    public function __construct(
        public int $code,
        public ErrorScope $scope,
        public FailureCategory $category,
        public ErrorAction $action,
        /** True when the code means the caller's credentials or certificate were refused. */
        public bool $authentication = false,
    ) {}
}
