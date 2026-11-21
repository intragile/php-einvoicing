<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use Closure;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use LogicException;
use SensitiveParameter;

/**
 * Holds one credential value out of sight.
 *
 * The value lives in a closure, which var_export() and json_encode() render empty, and the
 * object's own __debugInfo() hides that closure from var_dump() and print_r(), also when the
 * holder is cast to an array. It refuses to be serialized. This protects against first-level
 * dumps and casts only: code that deliberately casts this object itself to an array, or reflects
 * on the closure, can still reach the value.
 *
 * @internal
 */
final readonly class Secret
{
    /** @var Closure(): string */
    private Closure $value;

    public function __construct(#[SensitiveParameter] string $value)
    {
        $this->value = static fn (): string => $value;
    }

    public function reveal(): string
    {
        return ($this->value)();
    }

    /**
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => Redactor::REDACTED];
    }

    /**
     * @throws LogicException Always
     */
    public function __serialize(): never
    {
        throw new LogicException('A KSeF credential cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('A KSeF credential cannot be serialized.');
    }
}
