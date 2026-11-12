<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use InvalidArgumentException;

/**
 * Namespace bindings, in-scope variable names and declared user functions available while parsing
 * an expression.
 * The prefixes xs and fn are always bound to the XML Schema and the functions-and-operators namespace.
 *
 * @internal
 */
final readonly class StaticContext
{
    public const XS = 'http://www.w3.org/2001/XMLSchema';

    public const FN = 'http://www.w3.org/2005/xpath-functions';

    private const FIXED = ['xs' => self::XS, 'fn' => self::FN];

    /**
     * @param  array<string, string>  $namespaces  prefix => URI
     * @param  list<string>  $variables  names of the variables in scope
     * @param  array<string, true>  $functions  user functions callable as "{uri}local#arity"
     */
    public function __construct(public array $namespaces, public array $variables = [], public array $functions = [])
    {
        foreach (self::FIXED as $prefix => $uri) {
            if (isset($namespaces[$prefix]) && $namespaces[$prefix] !== $uri) {
                throw new InvalidArgumentException("The prefix {$prefix} must be bound to {$uri}.");
            }
        }
    }

    public function namespaceUri(string $prefix): ?string
    {
        return self::FIXED[$prefix] ?? $this->namespaces[$prefix] ?? null;
    }

    public function hasFunction(string $uri, string $local, int $arity): bool
    {
        return isset($this->functions[self::functionKey($uri, $local, $arity)]);
    }

    /**
     * The key of a user function in $functions, such as "{utils}gln#1".
     */
    public static function functionKey(string $uri, string $local, int $arity): string
    {
        return "{{$uri}}{$local}#{$arity}";
    }
}
