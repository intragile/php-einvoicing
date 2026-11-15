<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\FacturX;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Schematron\CompileOptions;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * The ids synthesized for the Factur-X asserts and reports that carry none never collide with an
 * id written in any of the five profile schemas, so a finding always names one rule.
 */
final class SynthesizedIdTest extends TestCase
{
    private const PREFIX = 'FX-REPORT';

    public function test_synthesized_ids_do_not_collide_with_explicit_ids(): void
    {
        $explicit = [];
        $synthesized = [];
        foreach (RuleFiles::profiles() as $profile) {
            [$explicit[$profile], $synthesized[$profile]] = self::sourceIds($profile);
            self::assertNotEmpty($synthesized[$profile], $profile);
            // Within one profile every id names exactly one rule; array_unique below only merges
            // ids shared across profiles.
            $ids = [...$explicit[$profile], ...$synthesized[$profile]];
            self::assertSame(count($ids), count(array_unique($ids)), $profile);

            $compiled = [];
            foreach (RuleSet::fromFile(RuleFiles::rules($profile))->patterns() as $pattern) {
                foreach ($pattern['rules'] as $rule) {
                    foreach ($rule['asserts'] as $assert) {
                        $compiled[] = $assert['id'];
                    }
                }
            }
            self::assertSame(self::sorted([...$explicit[$profile], ...$synthesized[$profile]]), self::sorted($compiled), $profile);
        }

        $allExplicit = array_unique(array_merge(...array_values($explicit)));
        $allSynthesized = array_unique(array_merge(...array_values($synthesized)));
        self::assertSame([], array_values(array_intersect($allSynthesized, $allExplicit)));
        self::assertSame([], array_values(array_filter($allExplicit, static fn (string $id): bool => str_starts_with($id, self::PREFIX.'-'))));
    }

    /**
     * The ids written in the profile schema and the ids the compiler synthesizes for the asserts
     * and reports without one, in document order.
     *
     * @return array{list<string>, list<string>}
     */
    private static function sourceIds(string $profile): array
    {
        $source = new DOMDocument;
        self::assertTrue($source->load(Fixtures::facturxSchematron($profile), LIBXML_NONET));
        $xpath = new DOMXPath($source);
        $xpath->registerNamespace('sch', 'http://purl.oclc.org/dsdl/schematron');

        $explicit = [];
        $synthesized = [];
        foreach ($xpath->query('//sch:rule/sch:assert | //sch:rule/sch:report') ?: [] as $assert) {
            self::assertInstanceOf(DOMElement::class, $assert);
            $rule = $assert->parentNode;
            self::assertInstanceOf(DOMElement::class, $rule);
            if ($assert->hasAttribute('id')) {
                $explicit[] = $assert->getAttribute('id');
            } else {
                $synthesized[] = CompileOptions::synthesizedId(self::PREFIX, $rule->getAttribute('context'), $assert->getAttribute('test'));
            }
        }

        return [$explicit, $synthesized];
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private static function sorted(array $ids): array
    {
        sort($ids, SORT_STRING);

        return $ids;
    }
}
