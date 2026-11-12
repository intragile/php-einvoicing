<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\Value\BooleanValue;
use IntrAgile\EInvoicing\XPath\Value\DecimalValue;
use IntrAgile\EInvoicing\XPath\Value\DoubleValue;
use IntrAgile\EInvoicing\XPath\Value\IntegerValue;
use IntrAgile\EInvoicing\XPath\Value\StringValue;
use IntrAgile\EInvoicing\XPath\Value\UntypedAtomic;
use LogicException;

/**
 * The XPath 2.0 functions used by the supported rule sets, with the function conversion rules
 * applied to their arguments (atomization, untyped values cast to the expected type, cardinality checks).
 *
 * @internal
 */
final class Functions
{
    private const XML_WHITESPACE = " \t\n\r";

    /** @var array<string, string> compiled PCRE patterns keyed by flags and XPath pattern */
    private static array $patterns = [];

    /**
     * @param  list<list<DOMNode|AtomicValue>>  $arguments  evaluated arguments
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError
     */
    public static function call(string $name, array $arguments, DOMNode|AtomicValue $context): array
    {
        $argument = static fn (int $index): array => $arguments[$index] ?? throw new LogicException("Missing argument {$index} of {$name}().");

        return match ($name) {
            'fn:abs' => self::abs(self::optionalNumeric($argument(0), $name)),
            'fn:boolean' => [new BooleanValue(self::boolean($argument(0)))],
            'fn:codepoints-to-string' => [new StringValue(self::codepointsToString(self::data($argument(0)), $name))],
            'fn:concat' => [new StringValue(implode('', array_map(static fn (array $value): string => self::optionalAtomic($value, $name)?->lexical() ?? '', $arguments)))],
            'fn:contains' => [new BooleanValue(str_contains(self::optionalString($argument(0), $name), self::optionalString($argument(1), $name)))],
            'fn:count' => [new IntegerValue(BigDecimal::of(count($argument(0))))],
            'fn:distinct-values' => self::distinctValues(self::data($argument(0))),
            'fn:empty' => [new BooleanValue($argument(0) === [])],
            'fn:ends-with' => [new BooleanValue(str_ends_with(self::optionalString($argument(0), $name), self::optionalString($argument(1), $name)))],
            'fn:exists' => [new BooleanValue($argument(0) !== [])],
            'fn:false' => [new BooleanValue(false)],
            'fn:local-name' => [new StringValue(self::localName(self::nodeArgument($arguments, $context, $name)))],
            'fn:matches' => [new BooleanValue(self::matches(
                self::optionalString($argument(0), $name),
                self::optionalString($argument(1), $name),
                isset($arguments[2]) ? self::optionalString($arguments[2], $name) : '',
            ))],
            'fn:name' => [new StringValue(self::name(self::nodeArgument($arguments, $context, $name)))],
            'fn:normalize-space' => [new StringValue(self::normalizeSpace(self::stringArgument($arguments, $context, $name)))],
            'fn:not' => [new BooleanValue(! self::boolean($argument(0)))],
            'fn:number' => [new DoubleValue(self::number(self::optionalAtomic($arguments === [] ? [$context] : $argument(0), $name)))],
            'fn:replace' => [new StringValue(self::replace(
                self::optionalString($argument(0), $name),
                self::requiredString($argument(1), $name),
                self::requiredString($argument(2), $name),
                isset($arguments[3]) ? self::requiredString($arguments[3], $name) : '',
            ))],
            'fn:reverse' => array_reverse($argument(0)),
            'fn:round' => self::round(self::optionalNumeric($argument(0), $name)),
            'fn:starts-with' => [new BooleanValue(str_starts_with(self::optionalString($argument(0), $name), self::optionalString($argument(1), $name)))],
            'fn:string' => [new StringValue(self::stringOf($arguments === [] ? [$context] : $argument(0), $name))],
            'fn:string-join' => [new StringValue(implode(
                self::requiredString($argument(1), $name),
                array_map(static fn (AtomicValue $value): string => $value->lexical(), self::data($argument(0))),
            ))],
            'fn:string-length' => [new IntegerValue(BigDecimal::of(mb_strlen(self::stringArgument($arguments, $context, $name), 'UTF-8')))],
            'fn:string-to-codepoints' => self::stringToCodepoints(self::optionalString($argument(0), $name)),
            'fn:substring' => [new StringValue(self::substring(
                self::optionalString($argument(0), $name),
                self::double($argument(1), $name),
                isset($arguments[2]) ? self::double($arguments[2], $name) : INF,
            ))],
            'fn:substring-after' => [new StringValue(self::substringAfter(self::optionalString($argument(0), $name), self::optionalString($argument(1), $name)))],
            'fn:substring-before' => [new StringValue(self::substringBefore(self::optionalString($argument(0), $name), self::optionalString($argument(1), $name)))],
            'fn:sum' => self::sum(self::data($argument(0)), isset($arguments[1]) ? $arguments[1] : null, $name),
            'fn:tokenize' => self::tokenize(
                self::optionalString($argument(0), $name),
                self::requiredString($argument(1), $name),
                isset($arguments[2]) ? self::requiredString($arguments[2], $name) : '',
            ),
            'fn:translate' => [new StringValue(self::translate(
                self::optionalString($argument(0), $name),
                self::requiredString($argument(1), $name),
                self::requiredString($argument(2), $name),
            ))],
            'fn:true' => [new BooleanValue(true)],
            'fn:upper-case' => [new StringValue(mb_strtoupper(self::optionalString($argument(0), $name), 'UTF-8'))],
            'xs:date', 'xs:decimal', 'xs:integer', 'xs:string' => self::construct($argument(0), $name),
            default => throw new LogicException("Unsupported function {$name}()."),
        };
    }

    /**
     * Atomization (fn:data): nodes become xs:untypedAtomic values of their string value.
     *
     * @param  list<DOMNode|AtomicValue>  $items
     * @return list<AtomicValue>
     */
    public static function data(array $items): array
    {
        $values = [];
        foreach ($items as $item) {
            $values[] = $item instanceof DOMNode ? new UntypedAtomic(self::stringValue($item)) : $item;
        }

        return $values;
    }

    /**
     * Effective boolean value.
     *
     * @param  list<DOMNode|AtomicValue>  $items
     *
     * @throws XPathError FORG0006 when the sequence has no effective boolean value
     */
    public static function boolean(array $items): bool
    {
        if ($items === []) {
            return false;
        }
        $first = $items[0];
        if ($first instanceof DOMNode) {
            return true;
        }
        if (count($items) > 1) {
            throw new XPathError('FORG0006', 'The effective boolean value of a sequence of several atomic values is not defined.');
        }

        return match (true) {
            $first instanceof BooleanValue => $first->value,
            $first instanceof StringValue, $first instanceof UntypedAtomic => $first->value !== '',
            $first instanceof IntegerValue, $first instanceof DecimalValue => ! $first->value->isZero(),
            $first instanceof DoubleValue => $first->value !== 0.0 && ! is_nan($first->value),
            default => throw new XPathError('FORG0006', "The effective boolean value of {$first->type()} is not defined."),
        };
    }

    public static function stringValue(DOMNode $node): string
    {
        if ($node instanceof DOMDocument) {
            return $node->documentElement->textContent ?? '';
        }

        return $node->textContent ?? '';
    }

    /**
     * @param  list<DOMNode|AtomicValue>  $items
     */
    private static function optionalAtomic(array $items, string $function): ?AtomicValue
    {
        $values = self::data($items);
        if (count($values) > 1) {
            throw new XPathError('XPTY0004', "{$function}() does not accept a sequence of more than one item.");
        }

        return $values[0] ?? null;
    }

    /**
     * An xs:string? argument: the empty sequence is the empty string.
     *
     * @param  list<DOMNode|AtomicValue>  $items
     */
    private static function optionalString(array $items, string $function): string
    {
        $value = self::optionalAtomic($items, $function);

        return match (true) {
            $value === null => '',
            $value instanceof StringValue, $value instanceof UntypedAtomic => $value->value,
            default => throw new XPathError('XPTY0004', "{$function}() expects xs:string, not {$value->type()}."),
        };
    }

    /**
     * A required xs:string argument: the empty sequence is a type error.
     *
     * @param  list<DOMNode|AtomicValue>  $items
     */
    private static function requiredString(array $items, string $function): string
    {
        if ($items === []) {
            throw new XPathError('XPTY0004', "{$function}() does not accept an empty sequence as a string argument.");
        }

        return self::optionalString($items, $function);
    }

    /**
     * A numeric? argument: untyped values are cast to xs:double.
     *
     * @param  list<DOMNode|AtomicValue>  $items
     */
    private static function optionalNumeric(array $items, string $function): IntegerValue|DecimalValue|DoubleValue|null
    {
        $value = self::optionalAtomic($items, $function);
        if ($value instanceof UntypedAtomic) {
            $value = Atomic::cast($value, 'xs:double');
        }

        return match (true) {
            $value === null, $value instanceof IntegerValue, $value instanceof DecimalValue, $value instanceof DoubleValue => $value,
            default => throw new XPathError('XPTY0004', "{$function}() expects a numeric value, not {$value->type()}."),
        };
    }

    /**
     * A required xs:double argument.
     *
     * @param  list<DOMNode|AtomicValue>  $items
     */
    private static function double(array $items, string $function): float
    {
        $value = self::optionalNumeric($items, $function);
        if ($value === null) {
            throw new XPathError('XPTY0004', "{$function}() does not accept an empty sequence as a numeric argument.");
        }

        $double = Atomic::cast($value, 'xs:double');

        return $double instanceof DoubleValue ? $double->value : throw new LogicException('Cast to xs:double failed.');
    }

    /**
     * The string argument of a function whose zero-argument form uses the context item.
     *
     * @param  list<list<DOMNode|AtomicValue>>  $arguments
     */
    private static function stringArgument(array $arguments, DOMNode|AtomicValue $context, string $function): string
    {
        return $arguments === [] ? self::stringOf([$context], $function) : self::optionalString($arguments[0], $function);
    }

    /**
     * fn:string of an item?.
     *
     * @param  list<DOMNode|AtomicValue>  $items
     */
    private static function stringOf(array $items, string $function): string
    {
        if (count($items) > 1) {
            throw new XPathError('XPTY0004', "{$function}() does not accept a sequence of more than one item.");
        }
        $item = $items[0] ?? null;

        return match (true) {
            $item === null => '',
            $item instanceof DOMNode => self::stringValue($item),
            default => $item->lexical(),
        };
    }

    /**
     * The node argument of fn:name and fn:local-name, or the context node without argument.
     *
     * @param  list<list<DOMNode|AtomicValue>>  $arguments
     */
    private static function nodeArgument(array $arguments, DOMNode|AtomicValue $context, string $function): ?DOMNode
    {
        $items = $arguments === [] ? [$context] : $arguments[0];
        if (count($items) > 1) {
            throw new XPathError('XPTY0004', "{$function}() does not accept a sequence of more than one node.");
        }
        $item = $items[0] ?? null;
        if ($item !== null && ! $item instanceof DOMNode) {
            throw new XPathError('XPTY0004', "{$function}() expects a node, not {$item->type()}.");
        }

        return $item;
    }

    private static function name(?DOMNode $node): string
    {
        return match (true) {
            $node instanceof DOMElement, $node instanceof DOMAttr => $node->nodeName,
            $node instanceof DOMProcessingInstruction => $node->target,
            default => '',
        };
    }

    private static function localName(?DOMNode $node): string
    {
        return match (true) {
            $node instanceof DOMElement, $node instanceof DOMAttr => $node->localName ?? $node->nodeName,
            $node instanceof DOMProcessingInstruction => $node->target,
            default => '',
        };
    }

    /**
     * Strips leading and trailing XML whitespace and collapses inner runs to one space; other
     * whitespace such as NO-BREAK SPACE is kept.
     */
    private static function normalizeSpace(string $value): string
    {
        return preg_replace('/[ \t\n\r]+/', ' ', trim($value, self::XML_WHITESPACE)) ?? $value;
    }

    /**
     * Characters at positions p (1-based, in code points) with round(start) <= p < round(start) + round(length).
     */
    private static function substring(string $value, float $start, float $length): string
    {
        $first = self::roundDouble($start);
        $end = $first + self::roundDouble($length);
        if (is_nan($first) || is_nan($end)) {
            return '';
        }

        $characters = mb_str_split($value, 1, 'UTF-8');
        $from = max(1.0, $first);
        $to = min((float) (count($characters) + 1), $end);
        if ($to <= $from) {
            return '';
        }

        return implode('', array_slice($characters, (int) $from - 1, (int) ($to - $from)));
    }

    private static function substringBefore(string $value, string $search): string
    {
        $position = $search === '' ? false : strpos($value, $search);

        return $position === false ? '' : substr($value, 0, $position);
    }

    private static function substringAfter(string $value, string $search): string
    {
        if ($search === '') {
            return $value;
        }
        $position = strpos($value, $search);

        return $position === false ? '' : substr($value, $position + strlen($search));
    }

    /**
     * @param  list<AtomicValue>  $values
     * @param  list<DOMNode|AtomicValue>|null  $zero
     * @return list<AtomicValue>
     */
    private static function sum(array $values, ?array $zero, string $function): array
    {
        if ($values === []) {
            if ($zero === null) {
                return [new IntegerValue(BigDecimal::zero())];
            }
            $zeroValue = self::optionalAtomic($zero, $function);

            return $zeroValue === null ? [] : [$zeroValue];
        }

        $total = null;
        foreach ($values as $value) {
            if ($value instanceof UntypedAtomic) {
                $value = Atomic::cast($value, 'xs:double');
            }
            if (! ($value instanceof IntegerValue || $value instanceof DecimalValue || $value instanceof DoubleValue)) {
                throw new XPathError('FORG0006', "{$function}() cannot add a value of type {$value->type()}.");
            }
            $total = $total === null ? $value : Atomic::arithmetic('+', $total, $value);
        }

        return [$total];
    }

    /**
     * fn:round: half towards positive infinity, keeping the numeric type.
     *
     * @return list<AtomicValue>
     */
    private static function round(IntegerValue|DecimalValue|DoubleValue|null $value): array
    {
        return match (true) {
            $value === null => [],
            $value instanceof IntegerValue => [$value],
            $value instanceof DecimalValue => [new DecimalValue($value->value->toScale(0, RoundingMode::HALF_CEILING))],
            default => [new DoubleValue(self::roundDouble($value->value))],
        };
    }

    private static function roundDouble(float $value): float
    {
        // NaN, infinities, zeros and doubles of 2^52 and beyond are already integral.
        if (is_nan($value) || is_infinite($value) || $value === 0.0 || abs($value) >= 4503599627370496.0) {
            return $value;
        }

        $floor = floor($value);
        // The difference is exact for doubles below 2^52, unlike $value + 0.5.
        $rounded = $value - $floor >= 0.5 ? $floor + 1.0 : $floor;

        return $rounded === 0.0 && $value < 0 ? -0.0 : $rounded;
    }

    /**
     * @return list<AtomicValue>
     */
    private static function abs(IntegerValue|DecimalValue|DoubleValue|null $value): array
    {
        return match (true) {
            $value === null => [],
            $value instanceof IntegerValue => [new IntegerValue($value->value->abs())],
            $value instanceof DecimalValue => [new DecimalValue($value->value->abs())],
            default => [new DoubleValue(abs($value->value))],
        };
    }

    /**
     * Distinct values by eq, keeping the first occurrence; untyped values compare as strings, NaN equals
     * NaN, and values of incomparable types are distinct.
     *
     * @param  list<AtomicValue>  $values
     * @return list<AtomicValue>
     */
    private static function distinctValues(array $values): array
    {
        $result = [];
        $strings = [];
        $others = [];
        foreach ($values as $value) {
            if ($value instanceof StringValue || $value instanceof UntypedAtomic) {
                if (! isset($strings[$value->value])) {
                    $strings[$value->value] = true;
                    $result[] = $value;
                }

                continue;
            }

            foreach ($others as $kept) {
                if (self::sameValue($kept, $value)) {
                    continue 2;
                }
            }
            $others[] = $value;
            $result[] = $value;
        }

        return $result;
    }

    private static function sameValue(AtomicValue $left, AtomicValue $right): bool
    {
        if ($left instanceof DoubleValue && $right instanceof DoubleValue && is_nan($left->value) && is_nan($right->value)) {
            return true;
        }

        try {
            return Atomic::valueCompare('eq', $left, $right);
        } catch (XPathError $error) {
            // Values of incomparable types are distinct; any other error propagates.
            if ($error->errorCode !== 'XPTY0004') {
                throw $error;
            }

            return false;
        }
    }

    private static function matches(string $input, string $pattern, string $flags): bool
    {
        $pcre = self::compile($pattern, $flags);
        $result = preg_match($pcre, $input);
        if ($result === false) {
            throw new XPathError('FORX0002', "The regular expression \"{$pattern}\" cannot be applied: ".preg_last_error_msg());
        }

        return $result === 1;
    }

    /**
     * fn:number: the value cast to xs:double, or NaN when it is empty or cannot be cast (Saxon
     * answers NaN for a non-numeric string as well as for a type such as xs:date).
     */
    private static function number(?AtomicValue $value): float
    {
        if ($value === null) {
            return NAN;
        }

        try {
            $double = Atomic::cast($value, 'xs:double');
        } catch (XPathError) {
            return NAN;
        }

        return $double instanceof DoubleValue ? $double->value : throw new LogicException('Cast to xs:double failed.');
    }

    /**
     * @return list<IntegerValue>
     */
    private static function stringToCodepoints(string $value): array
    {
        $codepoints = [];
        foreach (mb_str_split($value, 1, 'UTF-8') as $character) {
            $codepoints[] = new IntegerValue(BigDecimal::of(mb_ord($character, 'UTF-8')));
        }

        return $codepoints;
    }

    /**
     * fn:codepoints-to-string of an xs:integer* argument (untyped values are cast to xs:integer).
     *
     * @param  list<AtomicValue>  $values
     *
     * @throws XPathError FOCH0001 for a code point that is not a legal XML 1.0 character
     */
    private static function codepointsToString(array $values, string $function): string
    {
        $string = '';
        foreach ($values as $value) {
            if ($value instanceof UntypedAtomic) {
                $value = Atomic::cast($value, 'xs:integer');
            }
            if (! $value instanceof IntegerValue) {
                throw new XPathError('XPTY0004', "{$function}() expects xs:integer, not {$value->type()}.");
            }
            $codepoint = $value->value->isGreaterThan(0x10FFFF) || $value->value->isNegative() ? -1 : $value->value->toInt();
            $legal = $codepoint === 0x9 || $codepoint === 0xA || $codepoint === 0xD
                || ($codepoint >= 0x20 && $codepoint <= 0xD7FF)
                || ($codepoint >= 0xE000 && $codepoint <= 0xFFFD)
                || $codepoint >= 0x10000;
            if (! $legal) {
                throw new XPathError('FOCH0001', "{$value->lexical()} is not the code point of a legal XML character.");
            }
            $string .= mb_chr($codepoint, 'UTF-8');
        }

        return $string;
    }

    /**
     * fn:translate by code point: a character of $map is replaced by the character at the same
     * position in $trans, or removed when $trans is shorter; the first occurrence in $map wins.
     */
    private static function translate(string $value, string $map, string $trans): string
    {
        $replacements = [];
        $targets = mb_str_split($trans, 1, 'UTF-8');
        foreach (mb_str_split($map, 1, 'UTF-8') as $position => $character) {
            $replacements[$character] ??= $targets[$position] ?? '';
        }

        $result = '';
        foreach (mb_str_split($value, 1, 'UTF-8') as $character) {
            $result .= $replacements[$character] ?? $character;
        }

        return $result;
    }

    /**
     * @return list<StringValue>
     *
     * @throws XPathError FORX0003 when the pattern matches the empty string
     */
    private static function tokenize(string $input, string $pattern, string $flags): array
    {
        $pcre = self::nonEmptyPattern($pattern, $flags);
        if ($input === '') {
            return [];
        }

        $tokens = preg_split($pcre, $input);
        if ($tokens === false) {
            throw new XPathError('FORX0002', "The regular expression \"{$pattern}\" cannot be applied: ".preg_last_error_msg());
        }

        return array_map(static fn (string $token): StringValue => new StringValue($token), $tokens);
    }

    /**
     * @throws XPathError FORX0003 when the pattern matches the empty string, FORX0004 for an invalid replacement
     */
    private static function replace(string $input, string $pattern, string $replacement, string $flags): string
    {
        $pcre = self::nonEmptyPattern($pattern, $flags);
        $result = preg_replace($pcre, Regex::toReplacement($replacement, Regex::groupCount($pcre)), $input);
        if ($result === null) {
            throw new XPathError('FORX0002', "The regular expression \"{$pattern}\" cannot be applied: ".preg_last_error_msg());
        }

        return $result;
    }

    /**
     * The compiled pattern of fn:tokenize and fn:replace, which must not match the empty string.
     */
    private static function nonEmptyPattern(string $pattern, string $flags): string
    {
        $pcre = self::compile($pattern, $flags);
        if (preg_match($pcre, '') === 1) {
            throw new XPathError('FORX0003', "The regular expression \"{$pattern}\" matches the empty string.");
        }

        return $pcre;
    }

    private static function compile(string $pattern, string $flags): string
    {
        return self::$patterns[$flags.'/'.$pattern] ??= Regex::toPcre($pattern, $flags);
    }

    /**
     * The constructor functions xs:date(), xs:decimal(), xs:integer() and xs:string().
     *
     * @param  list<DOMNode|AtomicValue>  $items
     * @return list<AtomicValue>
     */
    private static function construct(array $items, string $type): array
    {
        $value = self::optionalAtomic($items, $type);

        return $value === null ? [] : [Atomic::cast($value, $type)];
    }
}
