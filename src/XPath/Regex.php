<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use LogicException;

/**
 * Translates XPath 2.0 regular expressions (XML Schema syntax plus anchors, back-references and
 * reluctant quantifiers) into PCRE patterns with the u modifier.
 *
 * @internal
 */
final class Regex
{
    /** XML Schema \w: every character outside the categories P, Z and C. */
    private const WORD = '\p{L}\p{M}\p{N}\p{S}';

    private const NOT_WORD = '\p{P}\p{Z}\p{C}';

    private const SPACE = '\x{20}\t\n\r';

    private const NOT_SPACE = '\x{0}-\x{8}\x{B}\x{C}\x{E}-\x{1F}\x{21}-\x{10FFFF}';

    /** Characters that may follow a backslash as a single-character escape. */
    private const SINGLE_ESCAPES = 'nrt\\|.?*+(){}-[]^$';

    private const FLAGS = ['s' => 's', 'm' => 'm', 'i' => 'i', 'x' => ''];

    /**
     * @param  string  $regex  an XPath regular expression
     * @param  string  $flags  any of s, m, i, x
     * @return string a complete PCRE pattern including delimiters and modifiers; matching is unanchored
     *
     * @throws UnsupportedXPath for \i, \c, \I, \C, Unicode block escapes (\p{IsBlock}), character class subtraction
     *                          and the XPath 3.0 flag q
     * @throws XPathError FORX0001 for invalid flags, FORX0002 for an invalid regular expression
     */
    public static function toPcre(string $regex, string $flags): string
    {
        $modifiers = 'Du';
        foreach (mb_str_split($flags, 1, 'UTF-8') as $flag) {
            if ($flag === 'q') {
                throw new UnsupportedXPath('regular expression flag q', $flags, 0);
            }
            $modifier = self::FLAGS[$flag] ?? throw new XPathError('FORX0001', "Invalid regular expression flag \"{$flag}\".");
            if ($modifier !== '' && ! str_contains($modifiers, $modifier)) {
                $modifiers .= $modifier;
            }
        }
        $extended = str_contains($flags, 'x');

        $characters = mb_str_split($regex, 1, 'UTF-8');
        $count = count($characters);
        $pattern = '';
        $inClass = false;

        for ($i = 0; $i < $count; $i++) {
            $character = $characters[$i];

            if ($character === '\\') {
                $next = $characters[++$i] ?? throw self::invalid($regex, 'it ends with a backslash');
                $pattern .= self::escape($next, $characters, $i, $inClass, $regex);

                continue;
            }

            if ($inClass) {
                if ($character === '[') {
                    if (str_ends_with($pattern, '-')) {
                        throw new UnsupportedXPath('character class subtraction in a regular expression', $regex, $i);
                    }
                    throw self::invalid($regex, 'it contains an unescaped "[" inside a character class');
                }
                if ($character === ']') {
                    $inClass = false;
                }
                $pattern .= $character === '/' ? '\/' : $character;

                continue;
            }

            if ($extended && str_contains(" \t\n\r", $character)) {
                continue;
            }

            $pattern .= match ($character) {
                '[' => self::openClass($characters, $i, $inClass),
                '(' => self::openGroup($characters, $i, $regex),
                '/' => '\/',
                '#' => '\#',
                default => $character,
            };
        }

        if ($inClass) {
            throw self::invalid($regex, 'a character class is not closed');
        }

        $pcre = '/'.$pattern.'/'.$modifiers;
        if (@preg_match($pcre, '') === false) {
            throw self::invalid($regex, preg_last_error_msg());
        }

        return $pcre;
    }

    /**
     * Translates an XPath replacement string (fn:replace) into a preg_replace replacement: "$N"
     * refers to a captured group, "\$" and "\\" stand for a literal "$" and "\". The digits after
     * "$" form the longest group number that does not exceed $groups, at least one digit: with two
     * groups, "$12" is group 1 followed by "2". A group that does not exist is replaced by the
     * empty string.
     *
     * @param  int  $groups  the number of capturing groups of the pattern
     *
     * @throws XPathError FORX0004 for a "\" or "$" that does not start one of these forms
     */
    public static function toReplacement(string $xpathReplacement, int $groups = 9): string
    {
        $replacement = '';
        $length = strlen($xpathReplacement);
        for ($i = 0; $i < $length; $i++) {
            $character = $xpathReplacement[$i];
            $next = $xpathReplacement[$i + 1] ?? '';

            if ($character === '\\') {
                if ($next !== '\\' && $next !== '$') {
                    throw self::invalidReplacement($xpathReplacement);
                }
                $replacement .= '\\'.$next;
                $i++;

                continue;
            }

            if ($character === '$') {
                if (! ctype_digit($next)) {
                    throw self::invalidReplacement($xpathReplacement);
                }
                $group = (int) $next;
                for ($i += 2; $i < $length && ctype_digit($xpathReplacement[$i]) && $group * 10 + (int) $xpathReplacement[$i] <= $groups; $i++) {
                    $group = $group * 10 + (int) $xpathReplacement[$i];
                }
                $i--;
                $replacement .= '${'.$group.'}';

                continue;
            }

            $replacement .= $character;
        }

        return $replacement;
    }

    /**
     * The number of capturing groups of a pattern returned by toPcre().
     */
    public static function groupCount(string $pcre): int
    {
        $end = strrpos($pcre, '/');
        if ($end === false || $end === 0) {
            throw new LogicException('Not a pattern returned by toPcre().');
        }

        // The empty alternative always matches, and every group is reported, unmatched ones as null.
        $probe = '/(?:'.substr($pcre, 1, $end - 1).')|'.substr($pcre, $end);
        if (preg_match($probe, '', $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
            throw new LogicException('Cannot count the groups of '.$pcre.'.');
        }

        return count($matches) - 1;
    }

    private static function invalidReplacement(string $replacement): XPathError
    {
        return new XPathError('FORX0004', "Invalid replacement string \"{$replacement}\": \"\\\" must be followed by \"\\\" or \"$\", and \"$\" by a digit.");
    }

    /**
     * @param  list<string>  $characters
     */
    private static function escape(string $next, array $characters, int &$i, bool $inClass, string $regex): string
    {
        switch ($next) {
            case 'i':
            case 'I':
            case 'c':
            case 'C':
                throw new UnsupportedXPath("\\{$next} in a regular expression", $regex, $i - 1);
            case 'w':
                return $inClass ? self::WORD : '['.self::WORD.']';
            case 'W':
                return $inClass ? self::NOT_WORD : '['.self::NOT_WORD.']';
            case 's':
                return $inClass ? self::SPACE : '['.self::SPACE.']';
            case 'S':
                return $inClass ? self::NOT_SPACE : '['.self::NOT_SPACE.']';
            case 'd':
            case 'D':
                return '\\'.$next;
            case 'p':
            case 'P':
                if (($characters[$i + 1] ?? '') !== '{') {
                    throw self::invalid($regex, "\\{$next} is not followed by a category");
                }
                $name = '';
                for ($j = $i + 2; ($characters[$j] ?? '}') !== '}'; $j++) {
                    $name .= $characters[$j];
                }
                if (str_starts_with($name, 'Is')) {
                    throw new UnsupportedXPath("Unicode block escape \\{$next}{{$name}} in a regular expression", $regex, $i - 1);
                }
                $i = $j;

                return '\\'.$next.'{'.$name.'}';
        }

        if (ctype_digit($next) && $next !== '0' && ! $inClass) {
            return '\\'.$next;
        }
        if (str_contains(self::SINGLE_ESCAPES, $next)) {
            return '\\'.$next;
        }

        throw self::invalid($regex, "\\{$next} is not a valid escape");
    }

    /**
     * @param  list<string>  $characters
     */
    private static function openClass(array $characters, int &$i, bool &$inClass): string
    {
        $inClass = true;
        if (($characters[$i + 1] ?? '') === '^') {
            $i++;

            return '[^';
        }

        return '[';
    }

    /**
     * @param  list<string>  $characters
     */
    private static function openGroup(array $characters, int $i, string $regex): string
    {
        if (($characters[$i + 1] ?? '') === '?') {
            throw self::invalid($regex, 'groups starting with "(?" are not part of XPath 2.0 regular expressions');
        }

        return '(';
    }

    private static function invalid(string $regex, string $reason): XPathError
    {
        return new XPathError('FORX0002', "Invalid regular expression \"{$regex}\": {$reason}.");
    }
}
