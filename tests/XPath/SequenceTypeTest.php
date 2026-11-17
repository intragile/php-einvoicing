<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\XPath;

use DOMDocument;
use DOMNode;
use IntrAgile\EInvoicing\XPath\SequenceType;
use IntrAgile\EInvoicing\XPath\XPathError;
use PHPUnit\Framework\TestCase;

final class SequenceTypeTest extends TestCase
{
    public function test_element_optional_accepts_empty_and_one_element(): void
    {
        [$root] = self::nodes();

        self::assertSame([], SequenceType::convert('element()?', []));
        self::assertSame([$root], SequenceType::convert('element()?', [$root]));
    }

    public function test_element_requires_exactly_one_element(): void
    {
        [$root] = self::nodes();

        self::assertSame([$root], SequenceType::convert('element()', [$root]));
        self::assertSame('XPTY0004', self::codeOf(static fn (): array => SequenceType::convert('element()', [])));
    }

    public function test_element_rejects_attribute_node_with_xpt_y0004(): void
    {
        [, $attribute] = self::nodes();

        self::assertSame('XPTY0004', self::codeOf(static fn (): array => SequenceType::convert('element()?', [$attribute])));
        self::assertSame('XTTE0780', self::codeOf(static fn (): array => SequenceType::convert('element()?', [$attribute], 'XTTE0780')));
    }

    public function test_element_rejects_two_elements(): void
    {
        [$root, , $child] = self::nodes();

        self::assertSame('XPTY0004', self::codeOf(static fn (): array => SequenceType::convert('element()?', [$root, $child])));
    }

    public function test_node_optional_accepts_any_node_kind(): void
    {
        [, $attribute] = self::nodes();

        self::assertSame([$attribute], SequenceType::convert('node()?', [$attribute]));
        self::assertSame([], SequenceType::convert('node()?', []));
    }

    public function test_item_star_passes_anything_through(): void
    {
        [$root, $attribute, $child] = self::nodes();

        self::assertSame([], SequenceType::convert('item()*', []));
        self::assertSame([$root, $attribute, $child], SequenceType::convert('item()*', [$root, $attribute, $child]));
    }

    private static function codeOf(callable $call): string
    {
        try {
            $call();
        } catch (XPathError $error) {
            return $error->errorCode;
        }
        self::fail('A type error was expected.');
    }

    /**
     * @return array{DOMNode, DOMNode, DOMNode} the root element, its attribute and a child element
     */
    private static function nodes(): array
    {
        $document = new DOMDocument;
        $document->loadXML('<r a="1"><c/></r>');
        $root = $document->documentElement;
        self::assertNotNull($root);
        $attribute = $root->getAttributeNode('a');
        $child = $root->firstChild;
        self::assertNotNull($child);

        return [$root, $attribute, $child];
    }
}
