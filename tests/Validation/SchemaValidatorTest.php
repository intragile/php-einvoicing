<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation;

use DOMDocument;
use IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase
{
    private const UBL_INVOICE_XSD = 'ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd';

    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /** Per-test scratch folder inside the repository's ignored build/ folder. */
    private string $scratch;

    protected function setUp(): void
    {
        $this->scratch = dirname(__DIR__, 2).'/build/schema-validator-test-'.bin2hex(random_bytes(4));
        self::assertTrue(mkdir($this->scratch, 0o777, true));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->scratch.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->scratch);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validInstances(): iterable
    {
        $dir = dirname(__DIR__).'/fixtures/official/xrechnung';

        yield 'UBL Invoice' => [$dir.'/testsuite-2026-08-31/instances/standard/01.01a-INVOICE_ubl.xml', self::UBL_INVOICE_XSD];
        yield 'UBL CreditNote' => [$dir.'/configuration-tests-2026-08-31/instances/processing-valid/ubl008.xml', 'ubl/2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd'];
        yield 'CII' => [$dir.'/testsuite-2026-08-31/instances/standard/01.01a-INVOICE_uncefact.xml', 'cii/16b/xsd/CrossIndustryInvoice_100pD16B.xsd'];
    }

    #[DataProvider('validInstances')]
    public function test_valid_ubl_has_no_schema_errors(string $instance, string $schema): void
    {
        $document = self::load($instance);

        self::assertSame([], (new SchemaValidator)->validate($document, self::xsd($schema)));
    }

    public function test_out_of_order_element_is_a_schema_error(): void
    {
        $xml = self::ublInvoice();
        $swapped = preg_replace(
            '~(<cbc:ID>123456XX</cbc:ID>)(\s+)(<cbc:IssueDate>2016-04-04</cbc:IssueDate>)~',
            '$3$2$1',
            $xml,
            1,
            $count,
        );
        self::assertSame(1, $count);
        self::assertIsString($swapped);

        $errors = (new SchemaValidator)->validate(SafeXml::loadPreservingWhitespace($swapped), self::xsd(self::UBL_INVOICE_XSD));

        self::assertNotSame([], $errors);
        self::assertSame(7, $errors[0]->line, 'the misplaced cbc:IssueDate now sits on line 7');
        self::assertStringContainsString('IssueDate', $errors[0]->message);
        self::assertStringNotContainsString("\n", $errors[0]->message);
        self::assertFalse($errors[0]->fatal);
    }

    public function test_unknown_element_is_a_schema_error(): void
    {
        $xml = str_replace(
            '<cbc:IssueDate>2016-04-04</cbc:IssueDate>',
            '<cbc:IssueDate>2016-04-04</cbc:IssueDate><cbc:Bogus>x</cbc:Bogus>',
            self::ublInvoice(),
        );

        $errors = (new SchemaValidator)->validate(SafeXml::loadPreservingWhitespace($xml), self::xsd(self::UBL_INVOICE_XSD));

        self::assertNotSame([], $errors);
        self::assertStringContainsString('Bogus', $errors[0]->message);
    }

    public function test_invalid_date_lexical_is_a_schema_error(): void
    {
        $xml = str_replace('<cbc:IssueDate>2016-04-04</cbc:IssueDate>', '<cbc:IssueDate>2026-13-45</cbc:IssueDate>', self::ublInvoice());

        $errors = (new SchemaValidator)->validate(SafeXml::loadPreservingWhitespace($xml), self::xsd(self::UBL_INVOICE_XSD));

        self::assertCount(1, $errors);
        self::assertSame(8, $errors[0]->line);
        self::assertStringContainsString('2026-13-45', $errors[0]->message);
    }

    /**
     * An instance hint naming a schema that would reject the document, or one that would accept
     * anything, changes nothing: only the schema passed to validate() counts.
     */
    public function test_instance_schema_location_is_ignored(): void
    {
        $rejecting = $this->scratch.'/rejecting.xsd';
        file_put_contents($rejecting, <<<'XSD'
            <?xml version="1.0" encoding="UTF-8"?>
            <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"
                       targetNamespace="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
                       elementFormDefault="qualified">
              <xs:element name="Invoice"><xs:complexType/></xs:element>
            </xs:schema>
            XSD);
        $permissive = $this->scratch.'/permissive.xsd';
        file_put_contents($permissive, <<<'XSD'
            <?xml version="1.0" encoding="UTF-8"?>
            <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"
                       targetNamespace="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
                       elementFormDefault="qualified">
              <xs:element name="Invoice">
                <xs:complexType><xs:sequence><xs:any processContents="skip" minOccurs="0" maxOccurs="unbounded"/></xs:sequence></xs:complexType>
              </xs:element>
            </xs:schema>
            XSD);

        $valid = self::withSchemaLocation(self::ublInvoice(), $rejecting);
        self::assertSame([], (new SchemaValidator)->validate($valid, self::xsd(self::UBL_INVOICE_XSD)));

        $invalidXml = str_replace('2016-04-04</cbc:IssueDate>', '2026-13-45</cbc:IssueDate>', self::ublInvoice());
        $baseline = (new SchemaValidator)->validate(SafeXml::loadPreservingWhitespace($invalidXml), self::xsd(self::UBL_INVOICE_XSD));
        $hinted = (new SchemaValidator)->validate(self::withSchemaLocation($invalidXml, $permissive), self::xsd(self::UBL_INVOICE_XSD));
        self::assertCount(1, $baseline);
        self::assertEquals($baseline, $hinted);
    }

    /**
     * Hints to an unreachable host and to a missing local file are never loaded: the result is
     * identical to the one without a hint.
     */
    public function test_no_network_during_schema_validation(): void
    {
        $xsd = self::xsd(self::UBL_INVOICE_XSD);
        $missing = 'file://'.$this->scratch.'/does-not-exist/x.xsd';
        $hints = [
            'none' => SafeXml::loadPreservingWhitespace(self::ublInvoice()),
            'http' => self::withSchemaLocation(self::ublInvoice(), 'http://evil.example/x.xsd'),
            'noNamespaceHttp' => self::withSchemaLocation(self::ublInvoice(), 'http://evil.example/y.xsd', noNamespace: true),
            'file' => self::withSchemaLocation(self::ublInvoice(), $missing),
        ];

        foreach ($hints as $name => $document) {
            self::assertSame([], (new SchemaValidator)->validate($document, $xsd), $name);
        }
    }

    public function test_network_schema_import_is_refused_before_any_io(): void
    {
        $uri = 'http://127.0.0.1:1/x.xsd';
        $xsd = $this->scratch.'/importing.xsd';
        file_put_contents($xsd, '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'
            .'<xs:import namespace="urn:remote" schemaLocation="'.$uri.'"/>'
            .'<xs:element name="a" type="xs:string"/></xs:schema>');
        $document = new DOMDocument;
        $document->loadXML('<a>x</a>');

        try {
            (new SchemaValidator)->validate($document, $xsd);
            self::fail('A network import must be refused.');
        } catch (UnusableSchema $e) {
            self::assertStringContainsString($uri, $e->getMessage());
        }
    }

    /**
     * A file URI with a host names a remote share (UNC path); only an empty host and "localhost"
     * mean this machine.
     */
    public function test_remote_file_host_is_refused(): void
    {
        file_put_contents($this->scratch.'/imported.xsd', '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:imported">'
            .'<xs:element name="b" type="xs:string"/></xs:schema>');
        $importing = static fn (string $uri): string => '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'
            .'<xs:import namespace="urn:imported" schemaLocation="'.$uri.'"/>'
            .'<xs:element name="a" type="xs:string"/></xs:schema>';
        $document = new DOMDocument;
        $document->loadXML('<a>x</a>');

        file_put_contents($this->scratch.'/local.xsd', $importing('file://localhost'.$this->scratch.'/imported.xsd'));
        self::assertSame([], (new SchemaValidator)->validate($document, $this->scratch.'/local.xsd'));

        $remote = 'file://fileserver.example/share/imported.xsd';
        file_put_contents($this->scratch.'/remote.xsd', $importing($remote));
        try {
            (new SchemaValidator)->validate($document, $this->scratch.'/remote.xsd');
            self::fail('A file URI with a remote host must be refused.');
        } catch (UnusableSchema $e) {
            self::assertStringContainsString('network access is refused', $e->getMessage());
            self::assertStringContainsString($remote, $e->getMessage());
        }
    }

    /**
     * Only the warnings that signal an unusable schema are taken over; any other warning raised
     * while validating reaches the error handler that was active before.
     */
    public function test_unrelated_warning_is_not_swallowed(): void
    {
        $missing = $this->scratch.'/not-there.txt';
        $document = new class extends DOMDocument
        {
            public string $missing = '';

            public function schemaValidate(string $filename, int $flags = 0): bool
            {
                file_get_contents($this->missing);

                return parent::schemaValidate($filename, $flags);
            }
        };
        $document->missing = $missing;
        $document->loadXML((string) SafeXml::loadPreservingWhitespace(self::ublInvoice())->saveXML());

        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = [$level, $message];

            return true;
        });
        try {
            $errors = (new SchemaValidator)->validate($document, self::xsd(self::UBL_INVOICE_XSD));
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $errors);
        self::assertCount(1, $warnings);
        // PHP 8.6 drops the file name from this message, so match the parts every version shares.
        [$level, $message] = $warnings[0];
        self::assertSame(E_WARNING, $level);
        self::assertStringStartsWith('file_get_contents(', $message);
        self::assertStringContainsString('Failed to open stream', $message);
    }

    public function test_previous_entity_loader_is_restored(): void
    {
        $sentinel = static fn (?string $public, string $system): ?string => null;
        $previous = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader($sentinel);
        try {
            (new SchemaValidator)->validate(
                SafeXml::loadPreservingWhitespace(self::ublInvoice()),
                self::xsd(self::UBL_INVOICE_XSD),
            );
            self::assertSame($sentinel, libxml_get_external_entity_loader());
        } finally {
            libxml_set_external_entity_loader($previous);
        }
    }

    public function test_libxml_error_state_is_restored(): void
    {
        $invalid = SafeXml::loadPreservingWhitespace(
            str_replace('2016-04-04</cbc:IssueDate>', '2026-13-45</cbc:IssueDate>', self::ublInvoice()),
        );
        $xsd = self::xsd(self::UBL_INVOICE_XSD);

        $original = libxml_use_internal_errors(false);
        try {
            self::assertNotSame([], (new SchemaValidator)->validate($invalid, $xsd));
            self::assertFalse(libxml_use_internal_errors(), 'internal errors were left enabled');

            libxml_use_internal_errors(true);
            libxml_clear_errors();
            self::assertNotSame([], (new SchemaValidator)->validate($invalid, $xsd));
            self::assertTrue(libxml_use_internal_errors(true), 'internal errors were disabled');
            self::assertSame([], libxml_get_errors(), 'validation errors leaked into the libxml buffer');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($original);
        }
    }

    public function test_missing_schema_file_is_refused(): void
    {
        $this->expectException(UnusableSchema::class);

        (new SchemaValidator)->validate(SafeXml::loadPreservingWhitespace(self::ublInvoice()), $this->scratch.'/no-such-schema.xsd');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unusableSchemas(): iterable
    {
        yield 'not XML' => ['this is not an XML Schema', 'Start tag expected'];
        yield 'unresolved type' => [
            '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:t="urn:x" targetNamespace="urn:x">'
            .'<xs:element name="Invoice" type="t:Missing"/></xs:schema>',
            'does not resolve to a(n) type definition',
        ];
        yield 'missing include' => [
            '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:x">'
            .'<xs:include schemaLocation="missing.xsd"/><xs:element name="Invoice"/></xs:schema>',
            'missing.xsd',
        ];
    }

    /**
     * A schema that cannot be parsed or compiled is a configuration fault, never a finding about
     * the document, and it leaves the caller's libxml state as it was.
     */
    #[DataProvider('unusableSchemas')]
    public function test_unusable_schema_throws_instead_of_reporting_schema_errors(string $schema, string $detail): void
    {
        $xsd = $this->scratch.'/unusable.xsd';
        file_put_contents($xsd, $schema);
        $document = SafeXml::loadPreservingWhitespace(self::ublInvoice());

        $original = libxml_use_internal_errors(false);
        try {
            (new SchemaValidator)->validate($document, $xsd);
            self::fail('No exception was thrown for an unusable schema.');
        } catch (UnusableSchema $exception) {
            self::assertStringContainsString($xsd, $exception->getMessage());
            self::assertStringContainsString($detail, $exception->getMessage());
            self::assertFalse(libxml_use_internal_errors(), 'internal errors were left enabled');
            self::assertSame([], libxml_get_errors(), 'schema errors leaked into the libxml buffer');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($original);
        }
    }

    private static function xsd(string $relative): string
    {
        return RuleFiles::schemaDir().'/'.$relative;
    }

    private static function ublInvoice(): string
    {
        $contents = file_get_contents(dirname(__DIR__).'/fixtures/official/xrechnung/testsuite-2026-08-31/instances/standard/01.01a-INVOICE_ubl.xml');
        self::assertIsString($contents);

        return $contents;
    }

    private static function load(string $file): DOMDocument
    {
        $contents = file_get_contents($file);
        self::assertIsString($contents);

        return SafeXml::loadPreservingWhitespace($contents);
    }

    private static function withSchemaLocation(string $xml, string $location, bool $noNamespace = false): DOMDocument
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $root = $document->documentElement;
        self::assertNotNull($root);
        if ($noNamespace) {
            $root->setAttributeNS(self::XSI, 'xsi:noNamespaceSchemaLocation', $location);
        } else {
            $root->setAttributeNS(self::XSI, 'xsi:schemaLocation', $root->namespaceURI.' '.$location);
        }

        return $document;
    }
}
