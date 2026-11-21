<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth;

use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\AuthTokenRequest;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\XadesSigner;
use IntrAgile\EInvoicing\Validation\SchemaError;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthTokenRequestTest extends TestCase
{
    private const SCHEMA_DIR = __DIR__.'/../../../../resources/poland/ksef-auth-2-1';

    private const CHALLENGE = '20250625-CR-20F5EE4000-DA48AE4124-46';

    /**
     * The bundle is byte-identical to the Ministry of Finance files pinned in SOURCE.md.
     */
    public function test_bundled_schema_matches_pinned_hashes(): void
    {
        $files = [
            'schemat_auth_v2-1.xsd' => 'd48477808e342892f62ef8bc7f0b134812f0536b3bb9cc8433d4fff2a926c44c',
            'LICENSE.txt' => 'f813e0850875814fd117d10803221c6032ef32fe3415e9f1d37fb1a115c3c07b',
        ];

        foreach ($files as $file => $sha256) {
            self::assertSame($sha256, hash_file('sha256', self::SCHEMA_DIR.'/'.$file), $file);
            self::assertStringContainsString($sha256, (string) file_get_contents(self::SCHEMA_DIR.'/SOURCE.md'), $file);
        }
        self::assertStringContainsString(
            'targetNamespace="'.AuthTokenRequest::NAMESPACE.'"',
            (string) file_get_contents(self::SCHEMA_DIR.'/schemat_auth_v2-1.xsd'),
        );
    }

    /**
     * @return iterable<string, array{ContextIdentifier, string}>
     */
    public static function requests(): iterable
    {
        yield 'NIP, certificate subject' => [ContextIdentifier::nip('5265877635'), 'certificateSubject'];
        yield 'NIP, certificate fingerprint' => [ContextIdentifier::nip('5265877635'), 'certificateFingerprint'];
        yield 'internal id' => [ContextIdentifier::internalId('5265877635-12345'), 'certificateSubject'];
    }

    #[DataProvider('requests')]
    public function test_auth_request_is_xsd_valid(ContextIdentifier $context, string $subjectType): void
    {
        $xml = AuthTokenRequest::xml(self::CHALLENGE, $context, $subjectType);

        self::assertSame([], self::schemaErrors($xml));

        $xpath = self::xpath($xml);
        self::assertSame(self::CHALLENGE, $xpath->evaluate('string(/a:AuthTokenRequest/a:Challenge)'));
        self::assertSame($context->value, $xpath->evaluate('string(/a:AuthTokenRequest/a:ContextIdentifier/a:'.$context->xmlElement().')'));
        self::assertSame($subjectType, $xpath->evaluate('string(/a:AuthTokenRequest/a:SubjectIdentifierType)'));
        self::assertStringNotContainsString('Signature', $xml);
    }

    /**
     * The NipVatUe pattern of the published schema ends with "$", which XML Schema regular
     * expressions treat as a literal character, so no real NIP-VAT UE value can pass it. The
     * request is otherwise valid: that facet is the only error.
     */
    public function test_nip_vat_ue_request_fails_only_the_schema_pattern_defect(): void
    {
        $context = ContextIdentifier::nipVatUe('5265877635-DE123456789');
        $errors = self::schemaErrors(AuthTokenRequest::xml(self::CHALLENGE, $context));

        self::assertCount(1, $errors);
        self::assertStringContainsString('NipVatUe', $errors[0]->message);
        self::assertStringContainsString("[facet 'pattern']", $errors[0]->message);
        self::assertStringEndsWith("\$'.", $errors[0]->message);
    }

    /**
     * The schema does not declare the enveloped signature, so the signed request is validated
     * with it removed, and what remains is exactly the unsigned request.
     */
    public function test_signed_auth_request_is_xsd_valid(): void
    {
        $unsigned = AuthTokenRequest::xml(self::CHALLENGE, ContextIdentifier::nip('5265877635'));
        $signed = (new XadesSigner)->sign($unsigned, XadesSignerTest::seal());

        $document = new DOMDocument;
        self::assertTrue($document->loadXML($signed));
        $signature = $document->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
        self::assertNotNull($signature);
        $signature->parentNode?->removeChild($signature);

        self::assertSame([], (new SchemaValidator)->validate($document, self::SCHEMA_DIR.'/schemat_auth_v2-1.xsd'));

        $original = new DOMDocument;
        self::assertTrue($original->loadXML($unsigned));
        self::assertSame($original->C14N(true, false), $document->C14N(true, false));
    }

    public function test_unknown_subject_type_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AuthTokenRequest::xml(self::CHALLENGE, ContextIdentifier::nip('5265877635'), 'certificate');
    }

    public function test_context_identifier_json_and_element(): void
    {
        self::assertSame(['type' => 'Nip', 'value' => '5265877635'], ContextIdentifier::nip('5265877635')->json());
        self::assertSame('Nip', ContextIdentifier::nip('5265877635')->xmlElement());
        self::assertSame(['type' => 'InternalId', 'value' => '5265877635-12345'], ContextIdentifier::internalId('5265877635-12345')->json());
        self::assertSame('InternalId', ContextIdentifier::internalId('5265877635-12345')->xmlElement());
        self::assertSame(['type' => 'NipVatUe', 'value' => '5265877635-DE123456789'], ContextIdentifier::nipVatUe('5265877635-DE123456789')->json());
        self::assertSame('NipVatUe', ContextIdentifier::nipVatUe('5265877635-DE123456789')->xmlElement());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedIdentifiers(): iterable
    {
        yield 'NIP too short' => ['nip', '526587763'];
        yield 'NIP with leading zero' => ['nip', '0265877635'];
        yield 'NIP with prefix' => ['nip', 'PL5265877635'];
        yield 'internal id without suffix' => ['internalId', '5265877635'];
        yield 'internal id with short suffix' => ['internalId', '5265877635-1234'];
        yield 'NIP-VAT UE without VAT number' => ['nipVatUe', '5265877635-'];
        yield 'NIP-VAT UE with a bad NIP' => ['nipVatUe', '0265877635-DE123456789'];
    }

    #[DataProvider('malformedIdentifiers')]
    public function test_malformed_context_identifier_is_refused(string $factory, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        ContextIdentifier::{$factory}($value);
    }

    /**
     * @return list<SchemaError>
     */
    private static function schemaErrors(string $xml): array
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));

        return (new SchemaValidator)->validate($document, self::SCHEMA_DIR.'/schemat_auth_v2-1.xsd');
    }

    private static function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('a', AuthTokenRequest::NAMESPACE);

        return $xpath;
    }
}
