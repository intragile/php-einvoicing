<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle\Kosit;

use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Checks the mutation builder offline: no KoSIT daemon is needed.
 */
final class MutationsTest extends TestCase
{
    private const XML = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\"   >\n  <cbc:ID>1</cbc:ID>\n</Invoice>";

    public function test_a_change_that_alters_nothing_throws_although_the_source_text_differs(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not change its base');

        // Re-serialisation alone drops the extra spaces in the start tag.
        Mutations::apply(self::XML, static function (DOMXPath $xpath, string $syntax): void {}, 'no-op');
    }

    public function test_a_real_change_is_returned(): void
    {
        $mutated = Mutations::apply(self::XML, static function (DOMXPath $xpath, string $syntax): void {
            $nodes = $xpath->query('//cbc:ID');
            $node = $nodes === false ? null : $nodes->item(0);
            self::assertInstanceOf(DOMElement::class, $node);
            $node->textContent = '2';
        }, 'changed');

        self::assertStringContainsString('<cbc:ID>2</cbc:ID>', $mutated);
    }

    public function test_every_mutation_is_distinct_from_its_base_and_from_each_other(): void
    {
        $all = Mutations::all() + Mutations::dynamicErrors();

        self::assertCount(47, $all);
        self::assertCount(47, array_unique($all));
    }

    public function test_doctype_document_is_refused_as_unsafe(): void
    {
        $xml = Mutations::withDoctype();
        self::assertStringContainsString('<!DOCTYPE', $xml);

        $this->expectException(UnsafeXml::class);
        SafeXml::loadPreservingWhitespace($xml);
    }
}
