<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DOMDocument;
use IntrAgile\EInvoicing\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MutatorTest extends TestCase
{
    public function test_same_seed_gives_same_mutations(): void
    {
        $xml = self::example('ubl', 'guide-example1.xml');

        self::assertSame(Mutator::mutations($xml, 42, 30), Mutator::mutations($xml, 42, 30));
        self::assertNotSame(Mutator::mutations($xml, 42, 30), Mutator::mutations($xml, 43, 30));
    }

    public function test_labels_and_single_mutations_agree_with_the_full_list(): void
    {
        $xml = self::example('ubl', 'guide-example1.xml');
        $mutations = Mutator::mutations($xml, 9, 20);

        self::assertSame(array_column($mutations, 0), Mutator::labels($xml, 9, 20));
        self::assertSame($mutations[7][1], Mutator::mutation($xml, 9, 20, $mutations[7][0]));
    }

    public function test_every_kind_appears_in_200_mutations(): void
    {
        $kinds = self::kinds(Mutator::mutations(self::example('ubl', 'guide-example1.xml'), 1, 200));

        // guide-example1.xml has no invoice or line period, so start and end dates cannot be swapped there.
        self::assertSame(array_values(array_diff(Mutator::KINDS, ['swap-dates'])), $kinds);
    }

    public function test_dates_of_a_period_are_swapped(): void
    {
        $xml = self::example('ubl', 'guide-example2.xml');
        $swaps = array_values(array_filter(
            Mutator::mutations($xml, 1, 200),
            static fn (array $mutation): bool => str_starts_with($mutation[0], 'swap-dates '),
        ));

        self::assertNotEmpty($swaps);
        self::assertStringContainsString('<cbc:StartDate>2013-06-30</cbc:StartDate>', $swaps[0][1]);
        self::assertStringContainsString('<cbc:EndDate>2013-06-01</cbc:EndDate>', $swaps[0][1]);
    }

    public function test_cii_mutations_cover_cii_specific_kinds(): void
    {
        $kinds = self::kinds(Mutator::mutations(self::example('cii', 'CII_example5.xml'), 7, 200));

        self::assertContains('swap-dates', $kinds);
        self::assertContains('amount-abc', $kinds);
        self::assertContains('code-zzz', $kinds);
    }

    public function test_every_mutation_is_well_formed_changed_and_uniquely_labelled(): void
    {
        $xml = self::example('ubl', 'guide-example1.xml');
        $mutations = Mutator::mutations($xml, 1, 200);
        $original = self::canonical($xml);

        self::assertCount(200, $mutations);
        self::assertCount(200, array_unique(array_column($mutations, 0)));
        foreach ($mutations as [$label, $mutated]) {
            $document = new DOMDocument;
            self::assertTrue($document->loadXML($mutated, LIBXML_NONET), $label);
            self::assertNotSame($original, $document->C14N(), $label);
            self::assertMatchesRegularExpression('~^[a-z-]+ (/[\w.:-]+\[\d+\])+(/@[\w:]+)?$~', $label);
        }
    }

    /**
     * @param  list<array{string, string}>  $mutations
     * @return list<string>
     */
    private static function kinds(array $mutations): array
    {
        $kinds = array_unique(array_map(static fn (array $mutation): string => explode(' ', $mutation[0])[0], $mutations));

        return array_values(array_filter(Mutator::KINDS, static fn (string $kind): bool => in_array($kind, $kinds, true)));
    }

    private static function canonical(string $xml): string
    {
        $document = new DOMDocument;
        $document->loadXML($xml, LIBXML_NONET);

        return (string) $document->C14N();
    }

    private static function example(string $syntax, string $name): string
    {
        foreach (Fixtures::official($syntax) as $file) {
            if (basename($file) === $name) {
                return file_get_contents($file) ?: throw new RuntimeException("Cannot read {$file}.");
            }
        }

        throw new RuntimeException("Official example {$name} not found.");
    }
}
