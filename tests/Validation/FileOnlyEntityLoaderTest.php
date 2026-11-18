<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation;

use DOMDocument;
use IntrAgile\EInvoicing\Validation\FileOnlyEntityLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The temporary entity loader is exercised through real libxml schema compilation, which asks
 * the loader for every imported schema.
 */
final class FileOnlyEntityLoaderTest extends TestCase
{
    /** Per-test scratch folder inside the repository's ignored build/ folder. */
    private string $scratch;

    private bool $internalErrors = false;

    protected function setUp(): void
    {
        $this->scratch = dirname(__DIR__, 2).'/build/file-only-entity-loader-test-'.bin2hex(random_bytes(4));
        self::assertTrue(mkdir($this->scratch, 0o777, true));
        $this->internalErrors = libxml_use_internal_errors(true);
    }

    protected function tearDown(): void
    {
        libxml_clear_errors();
        libxml_use_internal_errors($this->internalErrors);
        foreach (glob($this->scratch.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->scratch);
    }

    public function test_local_import_loads(): void
    {
        file_put_contents($this->scratch.'/imported.xsd', self::importedSchema());
        $main = $this->mainSchema('imported.xsd');

        $valid = FileOnlyEntityLoader::run(fn (): bool => self::document()->schemaValidate($main), $refused);

        self::assertTrue($valid);
        self::assertSame([], $refused);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedUris(): iterable
    {
        yield 'http' => ['http://schemas.example/imported.xsd'];
        yield 'ftp' => ['ftp://schemas.example/imported.xsd'];
        yield 'php' => ['php://filter/resource=/etc/hosts'];
        yield 'file URI with a remote host' => ['file://fileserver.example/share/imported.xsd'];
    }

    #[DataProvider('refusedUris')]
    public function test_non_local_import_is_refused(string $uri): void
    {
        $main = $this->mainSchema($uri);

        $valid = FileOnlyEntityLoader::run(fn (): bool => @self::document()->schemaValidate($main), $refused);

        self::assertFalse($valid);
        self::assertSame([$uri], $refused);
    }

    public function test_previous_loader_is_restored_when_the_operation_throws(): void
    {
        $sentinel = static fn (?string $public, string $system): ?string => null;
        $previous = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader($sentinel);
        try {
            $caught = null;
            try {
                FileOnlyEntityLoader::run(static fn (): bool => self::fails('operation failed'), $refused);
            } catch (RuntimeException $e) {
                $caught = $e;
            }

            self::assertInstanceOf(RuntimeException::class, $caught);
            self::assertSame('operation failed', $caught->getMessage());
            self::assertSame($sentinel, libxml_get_external_entity_loader());
        } finally {
            libxml_set_external_entity_loader($previous);
        }
    }

    public function test_previous_loader_is_restored_after_a_normal_run(): void
    {
        file_put_contents($this->scratch.'/imported.xsd', self::importedSchema());
        $main = $this->mainSchema('imported.xsd');
        $sentinel = static fn (?string $public, string $system): ?string => null;
        $previous = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader($sentinel);
        try {
            $seen = null;
            $valid = FileOnlyEntityLoader::run(static function () use ($main, &$seen): bool {
                $seen = libxml_get_external_entity_loader();

                return self::document()->schemaValidate($main);
            }, $refused);

            self::assertTrue($valid);
            self::assertNotSame($sentinel, $seen, 'the temporary loader is active during the run');
            self::assertSame($sentinel, libxml_get_external_entity_loader());
        } finally {
            libxml_set_external_entity_loader($previous);
        }
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function prefixedUris(): iterable
    {
        yield 'file below the prefix' => ['http://schemas.example/base/imported.xsd', '/imported.xsd'];
        yield 'nested file below the prefix' => ['http://schemas.example/base/sub/imported.xsd', '/sub/imported.xsd'];
        yield 'parent segment' => ['http://schemas.example/base/../imported.xsd', null];
        yield 'nested parent segment' => ['http://schemas.example/base/sub/../../imported.xsd', null];
        yield 'trailing parent segment' => ['http://schemas.example/base/sub/..', null];
        yield 'backslash parent segment' => ['http://schemas.example/base/..\\imported.xsd', null];
        yield 'leading slash' => ['http://schemas.example/base//etc/hosts', null];
        yield 'outside the prefix' => ['http://schemas.example/other/imported.xsd', null];
    }

    /**
     * The loader is called directly, so the check does not depend on how libxml normalises a
     * schemaLocation before it asks for it.
     */
    #[DataProvider('prefixedUris')]
    public function test_prefix_resolves_only_below_its_folder(string $uri, ?string $relative): void
    {
        $prefixes = ['http://schemas.example/base/' => $this->scratch];

        $resolved = FileOnlyEntityLoader::run(static function () use ($uri): mixed {
            $loader = libxml_get_external_entity_loader();
            self::assertNotNull($loader);

            return $loader(null, $uri, []);
        }, $refused, $prefixes);

        if ($relative === null) {
            self::assertNull($resolved);
            self::assertSame([$uri], $refused);
        } else {
            self::assertSame($this->scratch.$relative, $resolved);
            self::assertSame([], $refused);
        }
    }

    private static function fails(string $message): bool
    {
        throw new RuntimeException($message);
    }

    /** Writes a schema that imports $location for the element <b:value> and returns its path. */
    private function mainSchema(string $location): string
    {
        $path = $this->scratch.'/main.xsd';
        file_put_contents($path, '<?xml version="1.0"?>'
            .'<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:b="urn:example:b" targetNamespace="urn:example:a" elementFormDefault="qualified">'
            .'<xs:import namespace="urn:example:b" schemaLocation="'.htmlspecialchars($location, ENT_XML1).'"/>'
            .'<xs:element name="root"><xs:complexType><xs:sequence><xs:element ref="b:value"/></xs:sequence></xs:complexType></xs:element>'
            .'</xs:schema>');

        return $path;
    }

    private static function importedSchema(): string
    {
        return '<?xml version="1.0"?>'
            .'<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:example:b" elementFormDefault="qualified">'
            .'<xs:element name="value" type="xs:string"/>'
            .'</xs:schema>';
    }

    private static function document(): DOMDocument
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML('<root xmlns="urn:example:a"><value xmlns="urn:example:b">x</value></root>'));

        return $document;
    }
}
