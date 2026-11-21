<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth;

use Brick\Math\BigInteger;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\AuthTokenRequest;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\XadesSigner;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\SelfSignedCertificate;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class XadesSignerTest extends TestCase
{
    private const DS = 'http://www.w3.org/2000/09/xmldsig#';

    private const XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    private const EXC_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    private const CHALLENGE = '20250625-CR-20F5EE4000-DA48AE4124-46';

    private static ?CertificateCredentials $seal = null;

    public function test_signature_verifies_with_openssl(): void
    {
        $credentials = self::seal();
        $xpath = self::xpath((new XadesSigner)->sign(self::request(), $credentials));

        $documentReference = self::element($xpath, '//ds:SignedInfo/ds:Reference[@URI=""]');
        self::assertSame(self::documentDigest($xpath->document), self::digestValue($xpath, $documentReference));

        $propertiesReference = self::element($xpath, '//ds:SignedInfo/ds:Reference[@URI="#SignedProperties-1"]');
        $signedProperties = self::element($xpath, '//xades:SignedProperties[@Id="SignedProperties-1"]');
        self::assertSame(
            base64_encode(hash('sha256', (string) $signedProperties->C14N(true, false), true)),
            self::digestValue($xpath, $propertiesReference),
        );

        $signedInfo = self::element($xpath, '//ds:SignedInfo');
        $signature = base64_decode(self::element($xpath, '//ds:SignatureValue')->textContent, true);
        self::assertIsString($signature);
        self::assertSame(1, openssl_verify((string) $signedInfo->C14N(true, false), $signature, $credentials->certificatePem, OPENSSL_ALGO_SHA256));
    }

    public function test_shape_matches_spike(): void
    {
        $credentials = self::seal();
        $xpath = self::xpath((new XadesSigner)->sign(self::request(), $credentials));
        $root = $xpath->document->documentElement;
        self::assertInstanceOf(DOMElement::class, $root);

        $last = $root->lastChild;
        self::assertInstanceOf(DOMElement::class, $last);
        self::assertSame(self::DS, $last->namespaceURI);
        self::assertSame('Signature', $last->localName);
        self::assertSame('Signature-1', $last->getAttribute('Id'));

        self::assertSame(self::EXC_C14N, self::element($xpath, '//ds:SignedInfo/ds:CanonicalizationMethod')->getAttribute('Algorithm'));
        self::assertSame('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', self::element($xpath, '//ds:SignedInfo/ds:SignatureMethod')->getAttribute('Algorithm'));

        $references = $xpath->query('//ds:SignedInfo/ds:Reference');
        self::assertNotFalse($references);
        self::assertSame(2, $references->length);

        $transforms = [];
        foreach (self::nodes($xpath, '//ds:Reference[@URI=""]/ds:Transforms/ds:Transform') as $transform) {
            $transforms[] = $transform->getAttribute('Algorithm');
        }
        self::assertSame(['http://www.w3.org/2000/09/xmldsig#enveloped-signature', self::EXC_C14N], $transforms);

        $properties = self::element($xpath, '//ds:Reference[@URI="#SignedProperties-1"]');
        self::assertSame('http://uri.etsi.org/01903#SignedProperties', $properties->getAttribute('Type'));
        self::assertSame(self::EXC_C14N, self::element($xpath, '//ds:Reference[@URI="#SignedProperties-1"]/ds:Transforms/ds:Transform')->getAttribute('Algorithm'));

        foreach (self::nodes($xpath, '//ds:DigestMethod') as $method) {
            self::assertSame('http://www.w3.org/2001/04/xmlenc#sha256', $method->getAttribute('Algorithm'));
        }

        $der = self::der($credentials->certificatePem);
        self::assertSame(base64_encode($der), self::element($xpath, '//ds:KeyInfo/ds:X509Data/ds:X509Certificate')->textContent);
        self::assertSame('#Signature-1', self::element($xpath, '//ds:Object/xades:QualifyingProperties')->getAttribute('Target'));
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', self::element($xpath, '//xades:SignedSignatureProperties/xades:SigningTime')->textContent);
        self::assertSame(
            base64_encode(hash('sha256', $der, true)),
            self::element($xpath, '//xades:SigningCertificate/xades:Cert/xades:CertDigest/ds:DigestValue')->textContent,
        );

        // A self-signed certificate: the issuer is the subject, in RFC 4514 (reversed) order.
        $issuer = self::element($xpath, '//xades:Cert/xades:IssuerSerial/ds:X509IssuerName')->textContent;
        self::assertMatchesRegularExpression('/\ACN=Test Organization, organizationIdentifier=VATPL-\d{10}, O=Test Organization, C=PL\z/', $issuer);
    }

    public function test_serial_number_is_decimal(): void
    {
        self::assertSame(
            '95108154671367718803909668895757076750560747511',
            XadesSigner::decimalSerial('10A8CC125553FC5377BEB45A82C810C825FD5FF7'),
        );

        $credentials = self::seal();
        $xpath = self::xpath((new XadesSigner)->sign(self::request(), $credentials));
        $serial = self::element($xpath, '//xades:IssuerSerial/ds:X509SerialNumber')->textContent;
        $parsed = openssl_x509_parse($credentials->certificatePem);
        self::assertIsArray($parsed);

        self::assertMatchesRegularExpression('/\A\d+\z/', $serial);
        $hex = $parsed['serialNumberHex'];
        if (! is_string($hex) || $hex === '') {
            self::fail('The certificate has no serial number.');
        }
        self::assertSame((string) BigInteger::fromBase($hex, 16), $serial);
    }

    public function test_ecdsa_signature_is_raw_64_bytes_and_verifies(): void
    {
        $credentials = self::credentials(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $xpath = self::xpath((new XadesSigner)->sign(self::request(), $credentials));

        self::assertSame('http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256', self::element($xpath, '//ds:SignedInfo/ds:SignatureMethod')->getAttribute('Algorithm'));
        self::assertSame(self::documentDigest($xpath->document), self::digestValue($xpath, self::element($xpath, '//ds:Reference[@URI=""]')));

        $raw = base64_decode(self::element($xpath, '//ds:SignatureValue')->textContent, true);
        self::assertIsString($raw);
        self::assertSame(64, strlen($raw));

        $signedInfo = (string) self::element($xpath, '//ds:SignedInfo')->C14N(true, false);
        self::assertSame(1, openssl_verify($signedInfo, self::rawToDer($raw), $credentials->certificatePem, OPENSSL_ALGO_SHA256));
    }

    public function test_rsa_1024_is_refused(): void
    {
        $credentials = self::credentials(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 1024]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('2048');

        (new XadesSigner)->sign(self::request(), $credentials);
    }

    public function test_ec_curve_other_than_p256_is_refused(): void
    {
        $credentials = self::credentials(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);

        $this->expectException(InvalidArgumentException::class);

        (new XadesSigner)->sign(self::request(), $credentials);
    }

    public function test_tampered_challenge_breaks_digest(): void
    {
        $signed = (new XadesSigner)->sign(self::request(), self::seal());
        $tampered = str_replace(self::CHALLENGE, '20250625-CR-20F5EE4000-DA48AE4124-47', $signed);
        self::assertNotSame($signed, $tampered);

        $xpath = self::xpath($tampered);
        self::assertNotSame(self::documentDigest($xpath->document), self::digestValue($xpath, self::element($xpath, '//ds:Reference[@URI=""]')));
    }

    public function test_signing_time_uses_clock(): void
    {
        $signer = new XadesSigner(static fn (): DateTimeImmutable => new DateTimeImmutable('2026-11-21T10:00:00+01:00'));
        $xpath = self::xpath($signer->sign(self::request(), self::seal()));

        // Backdated by one minute against clock skew, and always in UTC.
        self::assertSame('2026-11-21T08:59:00Z', self::element($xpath, '//xades:SigningTime')->textContent);
    }

    /**
     * URI="" covers the whole document: a processing instruction before the root element is
     * part of the digest, and the signature still verifies.
     */
    public function test_top_level_processing_instruction_is_covered_by_the_document_digest(): void
    {
        $credentials = self::seal();
        $xpath = self::xpath((new XadesSigner)->sign('<?pi x?><root>a</root>', $credentials));
        $digest = self::digestValue($xpath, self::element($xpath, '//ds:Reference[@URI=""]'));

        self::assertSame(self::documentDigest($xpath->document), $digest);
        self::assertNotSame(base64_encode(hash('sha256', '<root>a</root>', true)), $digest);
        self::assertSame(base64_encode(hash('sha256', "<?pi x?>\n<root>a</root>", true)), $digest);

        $signedInfo = (string) self::element($xpath, '//ds:SignedInfo')->C14N(true, false);
        $signature = base64_decode(self::element($xpath, '//ds:SignatureValue')->textContent, true);
        self::assertIsString($signature);
        self::assertSame(1, openssl_verify($signedInfo, $signature, $credentials->certificatePem, OPENSSL_ALGO_SHA256));
    }

    public function test_unparseable_xml_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new XadesSigner)->sign('<AuthTokenRequest>', self::seal());
    }

    public function test_issuer_name_is_rfc4514_escaped(): void
    {
        self::assertSame(
            'CN=Smith\, John, O=A\+B \"C\", OU=\#1, OU=x\\\\y\ , C=PL',
            XadesSigner::issuerName(['C' => 'PL', 'OU' => ['x\\y ', '#1'], 'O' => 'A+B "C"', 'CN' => 'Smith, John']),
        );
    }

    public static function seal(): CertificateCredentials
    {
        return self::$seal ??= SelfSignedCertificate::seal(SelfSignedCertificate::randomNip());
    }

    /**
     * A self-signed certificate for a freshly generated key of the given type.
     *
     * @param  array<string, int|string>  $keyOptions
     */
    public static function credentials(array $keyOptions): CertificateCredentials
    {
        $options = ['digest_alg' => 'sha256', 'config' => dirname(__DIR__, 4).'/resources/poland/ksef-testing/openssl.cnf'];
        $key = openssl_pkey_new($keyOptions + $options);
        self::assertNotFalse($key);
        $requestKey = $key;
        $csr = openssl_csr_new(['countryName' => 'PL', 'commonName' => 'Key Type Test'], $requestKey, $options);
        self::assertNotFalse($csr);
        self::assertNotTrue($csr);
        $certificate = openssl_csr_sign($csr, null, $key, 1, $options, random_int(1, PHP_INT_MAX));
        self::assertNotFalse($certificate);
        self::assertTrue(openssl_x509_export($certificate, $certificatePem));
        self::assertTrue(openssl_pkey_export($key, $keyPem, null, $options));
        self::assertIsString($certificatePem);
        self::assertIsString($keyPem);

        return new CertificateCredentials($certificatePem, $keyPem);
    }

    private static function request(): string
    {
        return AuthTokenRequest::xml(self::CHALLENGE, ContextIdentifier::nip('5265877635'));
    }

    private static function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ds', self::DS);
        $xpath->registerNamespace('xades', self::XADES);

        return $xpath;
    }

    private static function element(DOMXPath $xpath, string $expression): DOMElement
    {
        $nodes = self::nodes($xpath, $expression);
        self::assertCount(1, $nodes, $expression);

        return $nodes[0];
    }

    /**
     * @return list<DOMElement>
     */
    private static function nodes(DOMXPath $xpath, string $expression): array
    {
        $result = $xpath->query($expression);
        self::assertNotFalse($result);
        $nodes = [];
        foreach ($result as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $nodes[] = $node;
        }

        return $nodes;
    }

    private static function digestValue(DOMXPath $xpath, DOMElement $reference): string
    {
        $values = $reference->getElementsByTagNameNS(self::DS, 'DigestValue');
        self::assertSame(1, $values->length);

        return (string) $values->item(0)?->textContent;
    }

    /**
     * The digest of the whole document (URI="", comments excluded) after the enveloped-signature
     * and exclusive C14N transforms, recomputed independently of the signer.
     */
    private static function documentDigest(DOMDocument $document): string
    {
        $copy = new DOMDocument;
        self::assertTrue($copy->loadXML((string) $document->saveXML()));
        $xpath = new DOMXPath($copy);
        $xpath->registerNamespace('ds', self::DS);
        $signature = self::element($xpath, '/*/ds:Signature');
        $signature->parentNode?->removeChild($signature);

        return base64_encode(hash('sha256', (string) $copy->C14N(true, false), true));
    }

    private static function der(string $pem): string
    {
        return (string) base64_decode((string) preg_replace('/-----[^-]+-----|\s/', '', $pem), true);
    }

    /**
     * Converts an R||S ECDSA signature back to the DER SEQUENCE that OpenSSL verifies.
     */
    private static function rawToDer(string $raw): string
    {
        $integer = static function (string $bytes): string {
            $bytes = ltrim($bytes, "\x00");
            if ($bytes === '' || ord($bytes[0]) >= 0x80) {
                $bytes = "\x00".$bytes;
            }

            return "\x02".self::shortLength($bytes).$bytes;
        };
        $body = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32, 32));

        return "\x30".self::shortLength($body).$body;
    }

    /**
     * Encodes a DER short-form length, which covers every P-256 signature component.
     */
    private static function shortLength(string $content): string
    {
        $length = strlen($content);
        if ($length > 0x7F) {
            throw new LogicException('DER long-form lengths are not needed for P-256 signatures.');
        }

        return chr($length);
    }
}
