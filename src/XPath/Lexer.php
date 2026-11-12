<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use IntrAgile\EInvoicing\XPath\Exception\XPathSyntaxError;

/**
 * Splits an XPath 2.0 expression into tokens. Operator names such as "and" or "div" are returned as
 * names; whether they act as operators depends on the parser's position.
 *
 * @internal
 */
final class Lexer
{
    private const NCNAME = '[\p{L}_][\p{L}\p{N}\p{Mn}\p{Mc}_.\x{B7}-]*';

    /** Symbols, longest first so that "//" wins over "/"; ":=" is lexed so that "let" can be reported as unsupported. */
    private const SYMBOLS = ['//', '..', '::', ':=', '!=', '<=', '>=', '<<', '>>', '(', ')', '[', ']', ',', '/', '@', '.', '|', '=', '<', '>', '+', '-', '*', '?', '$'];

    /**
     * @return list<Token> the tokens, terminated by a Token::END token
     */
    public function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $offset = 0;

        while (true) {
            $offset += strspn($expression, " \t\r\n", $offset);
            if ($offset >= $length) {
                $tokens[] = new Token(Token::END, '', $length);

                return $tokens;
            }

            $token = $this->number($expression, $offset)
                ?? $this->string($expression, $offset)
                ?? $this->name($expression, $offset)
                ?? $this->symbol($expression, $offset);

            if ($token === null) {
                throw new XPathSyntaxError('Unexpected character "'.$expression[$offset].'"', $expression, $offset);
            }

            $tokens[] = $token[0];
            $offset += $token[1];
        }
    }

    /**
     * @return array{Token, int}|null
     */
    private function number(string $expression, int $offset): ?array
    {
        if (preg_match('/\G(?:\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?/', $expression, $match, 0, $offset) !== 1) {
            return null;
        }

        $type = match (true) {
            ($match[2] ?? '') !== '' => Token::DOUBLE,
            str_contains($match[0], '.') => Token::DECIMAL,
            default => Token::INTEGER,
        };

        return [new Token($type, $match[0], $offset), strlen($match[0])];
    }

    /**
     * @return array{Token, int}|null
     */
    private function string(string $expression, int $offset): ?array
    {
        $quote = $expression[$offset];
        if ($quote !== '"' && $quote !== "'") {
            return null;
        }

        $value = '';
        $start = $offset + 1;
        while (true) {
            $end = strpos($expression, $quote, $start);
            if ($end === false) {
                throw new XPathSyntaxError('Unterminated string literal', $expression, $offset);
            }
            $value .= substr($expression, $start, $end - $start);
            if (($expression[$end + 1] ?? '') !== $quote) {
                return [new Token(Token::STRING, $value, $offset), $end + 1 - $offset];
            }
            $value .= $quote;
            $start = $end + 2;
        }
    }

    /**
     * Reads an NCName, a QName, or a wildcard name test (prefix:* or *:local). A colon followed by
     * another colon is left alone, so "child::x" yields the name "child" followed by "::".
     *
     * @return array{Token, int}|null
     */
    private function name(string $expression, int $offset): ?array
    {
        $pattern = '/\G(?:\*:'.self::NCNAME.'|'.self::NCNAME.'(?::(?:'.self::NCNAME.'|\*))?)/u';
        if (preg_match($pattern, $expression, $match, 0, $offset) !== 1) {
            return null;
        }

        return [new Token(Token::NAME, $match[0], $offset), strlen($match[0])];
    }

    /**
     * @return array{Token, int}|null
     */
    private function symbol(string $expression, int $offset): ?array
    {
        foreach (self::SYMBOLS as $symbol) {
            if (substr_compare($expression, $symbol, $offset, strlen($symbol)) === 0) {
                return [new Token(Token::SYMBOL, $symbol, $offset), strlen($symbol)];
            }
        }

        return null;
    }
}
