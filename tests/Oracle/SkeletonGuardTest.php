<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\Mutator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * No compiled stylesheet of the Peppol rules is published, so the Peppol oracle compiles the
 * Schematron files itself with the ISO skeleton (SchematronToXslt). This guard proves that
 * pipeline faithful: the CEN 1.3.17 preprocessed Schematron compiled by it gives the same verdict
 * as the official CEN 1.3.17 stylesheet on every official example and 200 seeded mutations.
 */
#[Group('oracle')]
final class SkeletonGuardTest extends TestCase
{
    private const MUTATIONS = 200;

    private const MUTATIONS_PER_EXAMPLE = 20;

    /**
     * @return array<string, array{string, string}>
     */
    public static function examples(): array
    {
        $cases = [];
        foreach (['ubl', 'cii'] as $syntax) {
            foreach (Fixtures::official($syntax) as $file) {
                $cases["example {$syntax} ".basename($file)] = [$syntax, $file];
            }
        }

        return $cases;
    }

    /**
     * The first mutations of each example's seeded list (seed = crc32 of the file name), taken in
     * turn across the examples until there are 200. Documents are built in the test.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function mutations(): array
    {
        $labels = [];
        foreach (self::examples() as [$syntax, $file]) {
            $labels[] = [$syntax, $file, Mutator::labels(self::contents($file), crc32(basename($file)), self::MUTATIONS_PER_EXAMPLE)];
        }

        $cases = [];
        for ($round = 0; count($cases) < self::MUTATIONS && $round < self::MUTATIONS_PER_EXAMPLE; $round++) {
            foreach ($labels as [$syntax, $file, $list]) {
                if (isset($list[$round]) && count($cases) < self::MUTATIONS) {
                    $cases["mutation {$syntax} ".basename($file)." {$list[$round]}"] = [$syntax, $file, $list[$round]];
                }
            }
        }

        return $cases;
    }

    public function test_corpus_sizes(): void
    {
        self::assertCount(34, self::examples());
        self::assertCount(self::MUTATIONS, self::mutations());
    }

    /**
     * Guards against a vacuous pass: the compiled stylesheet must report failed assertions, with
     * their flags, on a CEN unit test that breaks a rule.
     */
    public function test_skeleton_stylesheet_reports_failed_assertions(): void
    {
        $document = Fixtures::unitTests()[0];
        $verdict = self::outcome(SchematronToXslt::compile(Fixtures::schematron($document->syntax)), $document->xml);

        self::assertNotSame([], $verdict);
        self::assertStringEndsWith(' fatal', $verdict[0]);
        self::assertSame(self::outcome(Fixtures::officialXslt($document->syntax), $document->xml), $verdict);
    }

    #[DataProvider('examples')]
    public function test_skeleton_matches_the_official_stylesheet_on_examples(string $syntax, string $file): void
    {
        $this->assertSameVerdict($syntax, self::contents($file));
    }

    #[DataProvider('mutations')]
    public function test_skeleton_matches_the_official_stylesheet_on_mutations(string $syntax, string $file, string $label): void
    {
        $this->assertSameVerdict($syntax, Mutator::mutation(self::contents($file), crc32(basename($file)), self::MUTATIONS_PER_EXAMPLE, $label));
    }

    private function assertSameVerdict(string $syntax, string $xml): void
    {
        self::assertSame(
            self::outcome(Fixtures::officialXslt($syntax), $xml),
            self::outcome(SchematronToXslt::compile(Fixtures::schematron($syntax)), $xml),
        );
    }

    /**
     * The verdict of a stylesheet, or the dynamic error code Saxon stopped with.
     *
     * @return list<string>
     */
    private static function outcome(string $xslt, string $xml): array
    {
        try {
            return OfficialRules::verdict(OfficialRules::run($xslt, $xml));
        } catch (OracleDynamicError $error) {
            return ['dynamic error '.$error->errorCode];
        }
    }

    private static function contents(string $file): string
    {
        return file_get_contents($file) ?: throw new RuntimeException("Cannot read {$file}.");
    }
}
