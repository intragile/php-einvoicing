<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\FacturX;

use DOMDocument;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Validation\FacturX\FacturXSources;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleFilesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function profiles(): iterable
    {
        yield 'MINIMUM' => ['MINIMUM', 55, 11];
        yield 'BASIC-WL' => ['BASIC-WL', 278, 59];
        yield 'BASIC' => ['BASIC', 385, 87];
        yield 'EN16931' => ['EN16931', 433, 194];
        yield 'EXTENDED' => ['EXTENDED', 943, 521];
    }

    public function test_version_matches_the_pinned_sources(): void
    {
        self::assertSame(FacturXSources::VERSION, RuleFiles::VERSION);
        self::assertSame(['MINIMUM', 'BASIC-WL', 'BASIC', 'EN16931', 'EXTENDED'], RuleFiles::profiles());
    }

    #[DataProvider('profiles')]
    public function test_every_profile_has_rules_and_schema(string $profile): void
    {
        $id = 'facturx-'.strtolower(str_replace('-', '', $profile));
        self::assertFileExists(RuleFiles::rules($profile));
        self::assertSame("{$id}-1.09.2.php", basename(RuleFiles::rules($profile)));
        self::assertFileExists(RuleFiles::schema($profile));
        self::assertSame('FACTUR-X_'.str_replace('-', '', $profile).'.xsd', basename(RuleFiles::schema($profile)));

        $rules = RuleSet::fromFile(RuleFiles::rules($profile));
        self::assertSame($id, $rules->id());
        self::assertSame('1.09.2', $rules->version());
        self::assertSame(hash_file('sha256', Fixtures::facturxSchematron($profile)), $rules->sourceSha256());
    }

    public function test_unknown_profile_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RuleFiles::rules('XRECHNUNG');
    }

    #[DataProvider('profiles')]
    public function test_compiled_counts_match_source(string $profile, int $asserts, int $reports): void
    {
        $source = new DOMDocument;
        self::assertTrue($source->load(Fixtures::facturxSchematron($profile), LIBXML_NONET));
        $count = static fn (string $element): int => $source->getElementsByTagNameNS('http://purl.oclc.org/dsdl/schematron', $element)->length;
        self::assertSame($asserts, $count('assert'));
        self::assertSame($reports, $count('report'));

        $compiled = self::countKinds(RuleSet::fromFile(RuleFiles::rules($profile)));
        self::assertSame([$asserts, $reports], $compiled);
    }

    #[DataProvider('profiles')]
    public function test_synthesized_ids_are_unique(string $profile): void
    {
        $ids = [];
        foreach (self::compiledAsserts(RuleSet::fromFile(RuleFiles::rules($profile))) as $assert) {
            $ids[] = $assert['id'];
        }

        $synthesized = array_values(array_filter($ids, static fn (string $id): bool => str_starts_with($id, 'FX-REPORT-')));
        self::assertNotEmpty($synthesized);
        self::assertSame($synthesized, array_values(array_unique($synthesized)));
    }

    #[DataProvider('profiles')]
    public function test_compiled_rules_embed_their_codedb(string $profile): void
    {
        $name = 'FACTUR-X_'.$profile.'_codedb.xml';
        $rules = RuleSet::fromFile(RuleFiles::rules($profile));
        $source = (string) file_get_contents(dirname(Fixtures::facturxSchematron($profile))."/{$name}");

        self::assertSame([$name => $source], $rules->documents());
        self::assertSame([$name => hash('sha256', $source)], $rules->documentSha256());
    }

    /**
     * Each shipped schema file is a byte-for-byte copy of the fixture at the same path, and the
     * set is closed: the profile schemas validate offline.
     */
    #[DataProvider('profiles')]
    public function test_schemas_are_unmodified_copies(string $profile): void
    {
        $shipped = glob(dirname(RuleFiles::schema($profile)).'/*.xsd');
        $fixtures = glob(Fixtures::facturxSchemaDir()."/{$profile}/*.xsd");
        self::assertIsArray($shipped);
        self::assertIsArray($fixtures);
        self::assertCount(4, $shipped);
        self::assertSame(array_map('basename', $fixtures), array_map('basename', $shipped));
        foreach ($shipped as $file) {
            self::assertFileEquals(Fixtures::facturxSchemaDir()."/{$profile}/".basename($file), $file);
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            self::assertTrue($document->loadXML(
                '<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"/>',
            ));
            $document->schemaValidate(RuleFiles::schema($profile), LIBXML_NONET);
            $messages = implode("\n", array_map(static fn (\LibXMLError $error): string => $error->message, libxml_get_errors()));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        // An empty root fails on its content model, not on an unresolved import.
        self::assertStringNotContainsString('failed to load', $messages);
        self::assertStringNotContainsString('Failed to locate', $messages);
        self::assertStringNotContainsString('Skipping import', $messages);
    }

    public function test_compiled_rules_are_up_to_date(): void
    {
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__, 3).'/bin/compile-facturx.php').' --check 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
    }

    /**
     * @return array{int, int} asserts, reports
     */
    private static function countKinds(RuleSet $rules): array
    {
        $asserts = $reports = 0;
        foreach (self::compiledAsserts($rules) as $assert) {
            $assert['report'] ? $reports++ : $asserts++;
        }

        return [$asserts, $reports];
    }

    /**
     * @return iterable<array{id: string, report: bool}>
     */
    private static function compiledAsserts(RuleSet $rules): iterable
    {
        foreach ($rules->patterns() as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                foreach ($rule['asserts'] as $assert) {
                    yield $assert;
                }
            }
        }
    }
}
