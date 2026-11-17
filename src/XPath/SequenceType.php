<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use Brick\Math\BigDecimal;
use DOMNode;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\Value\BooleanValue;
use IntrAgile\EInvoicing\XPath\Value\DecimalValue;
use IntrAgile\EInvoicing\XPath\Value\DoubleValue;
use IntrAgile\EInvoicing\XPath\Value\IntegerValue;
use IntrAgile\EInvoicing\XPath\Value\StringValue;
use IntrAgile\EInvoicing\XPath\Value\UntypedAtomic;
use LogicException;

/**
 * The conversion an XSLT "as" attribute applies to a parameter, a function result or a variable
 * (the function conversion rules), for the sequence types the compiler accepts (SUPPORTED).
 *
 * A node type (element(), element()?, node()?) and item()* keep the nodes: nothing is atomised, the
 * kind and the number of items are checked and a mismatch is the given type error.
 *
 * For an atomic type the value is atomised; an untyped item is cast to the type (a failing cast is FORG0001); an
 * xs:integer is accepted where an xs:decimal is expected and stays an xs:integer; an xs:double
 * given for an xs:decimal becomes the shortest decimal that reads back as that double, as Saxon 13
 * does after the XPath 4.0 coercion rules (NaN and the infinities cannot be converted). Any other
 * type, an empty sequence without "?" and more than one item are type errors. As in Saxon, the
 * items are converted one at a time and the cardinality error is raised on the second converted
 * item, so a failing cast of the first or the second item comes first.
 *
 * @internal
 */
final class SequenceType
{
    /** The sequence types an "as" attribute may carry. */
    public const SUPPORTED = ['xs:boolean', 'xs:integer', 'xs:decimal', 'xs:string', 'xs:string?', 'element()', 'element()?', 'node()?', 'item()*'];

    /** The supported sequence types that keep nodes instead of atomising them. */
    private const NODE_TYPES = ['element()', 'element()?', 'node()?', 'item()*'];

    /**
     * Whether the sequence type keeps nodes (element(), element()?, node()?, item()*) instead of
     * atomising the value.
     */
    public static function isNodeType(string $as): bool
    {
        return in_array($as, self::NODE_TYPES, true);
    }

    /**
     * @param  ?string  $as  the sequence type, or null for no conversion
     * @param  list<DOMNode|AtomicValue>  $value
     * @param  string  $typeError  the code of a type error: XPTY0004 for a parameter, XTTE0780 for a
     *                             function result, XTTE0570 for a variable
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError
     */
    public static function convert(?string $as, array $value, string $typeError = 'XPTY0004'): array
    {
        if ($as === null) {
            return $value;
        }
        if (self::isNodeType($as)) {
            return self::nodes($as, $value, $typeError);
        }
        $optional = str_ends_with($as, '?');
        $type = $optional ? substr($as, 0, -1) : $as;

        $result = [];
        foreach (Functions::data($value) as $item) {
            $converted = self::item($item, $type, $as, $typeError);
            if ($result !== []) {
                throw new XPathError($typeError, "A sequence of more than one item does not match the required type {$as}.");
            }
            $result[] = $converted;
        }
        if ($result === [] && ! $optional) {
            throw new XPathError($typeError, "An empty sequence does not match the required type {$as}.");
        }

        return $result;
    }

    /**
     * @param  list<DOMNode|AtomicValue>  $value
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError
     */
    private static function nodes(string $as, array $value, string $typeError): array
    {
        if ($as === 'item()*') {
            return $value;
        }
        $count = count($value);
        if ($count > 1 || ($count === 0 && ! str_ends_with($as, '?'))) {
            throw new XPathError($typeError, "A sequence of {$count} items does not match the required type {$as}.");
        }
        foreach ($value as $item) {
            $matches = $item instanceof DOMNode && ($as === 'node()?' || $item->nodeType === XML_ELEMENT_NODE);
            if (! $matches) {
                $found = $item instanceof DOMNode ? 'node of kind '.$item->nodeType : $item->type();
                throw new XPathError($typeError, "A value of type {$found} does not match the required type {$as}.");
            }
        }

        return $value;
    }

    /**
     * The decimal with the fewest significant digits that reads back as the same double, as Saxon
     * converts a double for an xs:decimal parameter (0.1e0 becomes 0.1; a cast keeps every binary
     * digit, 0.1000000000000000055511151231257827021181583404541015625).
     */
    private static function shortestDecimal(float $value): DecimalValue
    {
        $text = sprintf('%.16e', $value);
        for ($digits = 0; $digits < 16; $digits++) {
            $candidate = sprintf('%.'.$digits.'e', $value);
            if ((float) $candidate === $value) {
                $text = $candidate;
                break;
            }
        }

        return new DecimalValue(BigDecimal::of($text));
    }

    private static function item(AtomicValue $item, string $type, string $as, string $typeError): AtomicValue
    {
        if ($item instanceof UntypedAtomic) {
            return Atomic::cast($item, $type);
        }
        $matches = match ($type) {
            'xs:boolean' => $item instanceof BooleanValue,
            'xs:integer' => $item instanceof IntegerValue,
            'xs:decimal' => $item instanceof DecimalValue || $item instanceof IntegerValue,
            'xs:string' => $item instanceof StringValue,
            default => throw new LogicException("Unsupported sequence type {$as}."),
        };
        if ($matches) {
            return $item;
        }
        if ($type === 'xs:decimal' && $item instanceof DoubleValue && is_finite($item->value)) {
            return self::shortestDecimal($item->value);
        }

        throw new XPathError($typeError, "A value of type {$item->type()} does not match the required type {$as}.");
    }
}
