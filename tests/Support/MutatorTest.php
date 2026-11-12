<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DOMDocument;
use IntrAgile\EInvoicing\Tests\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function bothSyntaxes(): array
    {
        return ['ubl' => ['ubl', 'guide-example1.xml'], 'cii' => ['cii', 'CII_example5.xml']];
    }

    #[DataProvider('bothSyntaxes')]
    public function test_comment_and_space_are_inserted_before_leaf_text(string $syntax, string $name): void
    {
        $xml = self::example($syntax, $name);
        $inserts = array_values(array_filter(
            Mutator::mutations($xml, 3, 200),
            static fn (array $mutation): bool => str_starts_with($mutation[0], 'comment-space '),
        ));

        self::assertNotEmpty($inserts);
        foreach ($inserts as [$label, $mutated]) {
            $document = new DOMDocument;
            self::assertTrue($document->loadXML($mutated, LIBXML_NONET), $label);
            self::assertSame(1, preg_match('~<([\w:]+)(?: [^>]*)?> <!--x-->[^<]+</\1>~', $mutated), $label);
        }
    }

    public function test_cii_mutations_cover_cii_specific_kinds(): void
    {
        $kinds = self::kinds(Mutator::mutations(self::example('cii', 'CII_example5.xml'), 7, 200));

        self::assertContains('swap-dates', $kinds);
        self::assertContains('amount-abc', $kinds);
        self::assertContains('code-zzz', $kinds);
        self::assertContains('comment-space', $kinds);
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

    public function test_peppol_kinds_are_opt_in(): void
    {
        $xml = self::example('ubl', 'guide-example2.xml');

        self::assertSame(Mutator::mutations($xml, 5, 50), Mutator::mutations($xml, 5, 50, Mutator::KINDS));
        $kinds = array_map(static fn (string $label): string => explode(' ', $label)[0], Mutator::labels($xml, 5, 200));
        self::assertNotContains('check-digit', $kinds);
        self::assertNotContains('profile-id', $kinds);
    }

    public function test_check_digit_changes_the_last_digit_of_a_gln(): void
    {
        $xml = self::example('ubl', 'guide-example2.xml');
        $mutations = self::ofKind(Mutator::mutations($xml, 11, 200, Mutator::PEPPOL_KINDS), 'check-digit');

        self::assertNotEmpty($mutations);
        foreach ($mutations as [$label, $mutated]) {
            $before = self::glns($xml);
            $after = self::glns($mutated);
            $removed = array_values(array_diff($before, $after));
            $added = array_values(array_diff($after, $before));
            self::assertCount(1, $removed, $label);
            self::assertSame([substr($removed[0], 0, -1).(((int) substr($removed[0], -1)) + 1) % 10], $added, $label);
        }
    }

    public function test_check_digit_wraps_nine_to_zero_in_cii(): void
    {
        $xml = str_replace('>1234567890128<', '>1234567890129<', self::example('cii', 'CII_example2.xml'));
        $mutations = self::ofKind(Mutator::mutations($xml, 3, 300, ['check-digit']), 'check-digit');

        self::assertNotEmpty($mutations);
        $wrapped = array_filter($mutations, static fn (array $mutation): bool => str_contains($mutation[1], '>1234567890120<'));
        self::assertCount(1, $wrapped);
    }

    public function test_profile_id_is_replaced(): void
    {
        $xml = self::example('ubl', 'guide-example2.xml');
        $mutations = self::ofKind(Mutator::mutations($xml, 1, 50, ['profile-id']), 'profile-id');

        self::assertCount(1, $mutations);
        self::assertSame('profile-id /Invoice[1]/cbc:ProfileID[1]', $mutations[0][0]);
        self::assertStringContainsString('<cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>', $mutations[0][1]);

        $peppol = Mutator::mutations($mutations[0][1], 1, 50, ['profile-id']);
        self::assertStringContainsString('<cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:selfbilling:01:1.0</cbc:ProfileID>', $peppol[0][1]);
    }

    public function test_profile_id_in_cii_is_the_business_process_id(): void
    {
        $xml = '<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            .'<rsm:ExchangedDocumentContext><ram:BusinessProcessSpecifiedDocumentContextParameter><ram:ID>A1</ram:ID></ram:BusinessProcessSpecifiedDocumentContextParameter>'
            .'<ram:GuidelineSpecifiedDocumentContextParameter><ram:ID>urn:cen.eu:en16931:2017</ram:ID></ram:GuidelineSpecifiedDocumentContextParameter></rsm:ExchangedDocumentContext></rsm:CrossIndustryInvoice>';

        $mutations = Mutator::mutations($xml, 1, 10, ['profile-id']);

        self::assertCount(1, $mutations);
        self::assertStringContainsString('<ram:ID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</ram:ID></ram:BusinessProcess', $mutations[0][1]);
        self::assertStringContainsString('<ram:ID>urn:cen.eu:en16931:2017</ram:ID>', $mutations[0][1]);
    }

    public function test_unknown_kinds_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        Mutator::labels(self::example('ubl', 'guide-example1.xml'), 1, 5, ['explode']);
    }

    /**
     * The GLN identifiers (scheme 0088) of a document, in document order.
     *
     * @return list<string>
     */
    private static function glns(string $xml): array
    {
        preg_match_all('~schemeID="0088">(\d+)<~', $xml, $matches);

        return $matches[1];
    }

    /**
     * @param  list<array{string, string}>  $mutations
     * @return list<array{string, string}>
     */
    private static function ofKind(array $mutations, string $kind): array
    {
        return array_values(array_filter($mutations, static fn (array $mutation): bool => str_starts_with($mutation[0], $kind.' ')));
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
