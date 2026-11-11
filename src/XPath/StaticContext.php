<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use InvalidArgumentException;

/**
 * Namespace bindings and in-scope variable names available while parsing an expression.
 * The prefixes xs and fn are always bound to the XML Schema and the functions-and-operators namespace.
 */
final readonly class StaticContext
{
    public const XS = 'http://www.w3.org/2001/XMLSchema';

    public const FN = 'http://www.w3.org/2005/xpath-functions';

    private const FIXED = ['xs' => self::XS, 'fn' => self::FN];

    /**
     * @param  array<string, string>  $namespaces  prefix => URI
     * @param  list<string>  $variables  names of the variables in scope
     */
    public function __construct(public array $namespaces, public array $variables = [])
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
}
