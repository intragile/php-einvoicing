<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards the source against constructs that newer PHP versions deprecate, so a deprecation shows
 * up on every supported version and not only on the newest one.
 */
final class PhpCompatibilityTest extends TestCase
{
    public function test_no_class_constant_is_named_namespace(): void
    {
        // PHP 8.6 deprecates class constants called "namespace" (case-insensitive).
        $offenders = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../src', FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (self::constantNames((string) file_get_contents($file->getPathname())) as $name) {
                if (strcasecmp($name, 'namespace') === 0) {
                    $offenders[] = $file->getPathname().': '.$name;
                }
            }
        }

        self::assertSame([], $offenders);
    }

    public function test_the_scan_finds_every_form_of_constant_declaration(): void
    {
        $source = <<<'PHP'
            <?php
            /** const namespace = 'docblock' */
            final class A {
                public const string NAMESPACE = 'a';
                const B = 1, Namespace = 2;
                final protected const int | string /* c */ namespace = 3;
                const (X&Y)|null NameSpace = null;
                private const TEXT = 'const namespace = 1';
            }
            PHP;

        self::assertSame(['NAMESPACE', 'B', 'Namespace', 'namespace', 'NameSpace', 'TEXT'], self::constantNames($source));
    }

    /**
     * Names declared by every `const` statement of a PHP source, in source order. Tokens are used
     * instead of a pattern so that types, comments, several constants per statement and text in
     * strings or docblocks are handled the way PHP parses them.
     *
     * @return list<string>
     */
    private static function constantNames(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $names = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_CONST) {
                continue;
            }

            // Inside a const statement, a name is the last identifier before "=" at bracket depth 0.
            $depth = 0;
            $last = null;
            for ($j = $i + 1; $j < $count; $j++) {
                $token = $tokens[$j];
                if ($token === ';') {
                    break;
                }
                if ($token === '(' || $token === '[' || $token === '{') {
                    $depth++;
                } elseif ($token === ')' || $token === ']' || $token === '}') {
                    $depth--;
                } elseif ($depth === 0 && $token === '=' && $last !== null) {
                    $names[] = $last;
                    $last = null;
                    // Skip the value up to the next "," at depth 0 or the end of the statement.
                    for ($j++; $j < $count; $j++) {
                        $value = $tokens[$j];
                        if ($value === '(' || $value === '[' || $value === '{') {
                            $depth++;
                        } elseif ($value === ')' || $value === ']' || $value === '}') {
                            $depth--;
                        } elseif ($depth === 0 && ($value === ',' || $value === ';')) {
                            break;
                        }
                    }
                    if (($tokens[$j] ?? null) === ';') {
                        break;
                    }
                } elseif ($depth === 0 && is_array($token) && $token[0] === T_STRING) {
                    $last = $token[1];
                }
            }
        }

        return $names;
    }
}
