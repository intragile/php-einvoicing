<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\XPath;

use DOMDocument;
use IntrAgile\EInvoicing\XPath\Canonical;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\StaticContext;
use IntrAgile\EInvoicing\XPath\XPathError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the PHP evaluator against the Saxon-verified truth table of ProbeCases.
 */
final class ProbeTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function cases(): array
    {
        return ProbeCases::all();
    }

    #[DataProvider('cases')]
    public function test_evaluator_matches_expected_result(string $expression, string $xml, string $expected): void
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));
        $ast = (new Parser)->parse($expression, new StaticContext(ProbeCases::NAMESPACES));

        try {
            $actual = Canonical::of((new Evaluator(DocumentIndex::build($document)))->evaluate($ast, $document), ProbeCases::NAMESPACES);
        } catch (XPathError $error) {
            $actual = Canonical::error($error);
        }

        self::assertSame($expected, $actual, $expression);
    }
}
