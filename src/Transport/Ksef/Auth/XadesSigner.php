<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use Brick\Math\BigInteger;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Adds an enveloped XAdES-BES signature to an XML document, in the shape KSeF accepts for
 * POST /auth/xades-signature.
 *
 * The ds:Signature becomes the last child of the root element. Its SignedInfo is canonicalized
 * with exclusive C14N and carries two SHA-256 references: the whole document (URI="", with the
 * enveloped-signature and exclusive C14N transforms) and the XAdES SignedProperties. The
 * SignedProperties hold the signing time and the signing certificate (SHA-256 digest of its DER
 * encoding, issuer name in RFC 4514 order and the serial number in decimal). KeyInfo carries the
 * certificate.
 *
 * RSA keys of at least 2048 bits are signed with rsa-sha256. EC keys on P-256 are signed with
 * ecdsa-sha256, the signature value being the 64-byte concatenation R||S that XML Signature
 * requires instead of OpenSSL's DER encoding. Any other key is refused.
 */
final class XadesSigner
{
    public const DS_NAMESPACE = 'http://www.w3.org/2000/09/xmldsig#';

    public const XADES_NAMESPACE = 'http://uri.etsi.org/01903/v1.3.2#';

    private const EXCLUSIVE_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    private const ENVELOPED_SIGNATURE = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    private const SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';

    private const RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

    private const ECDSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256';

    private const SIGNED_PROPERTIES_TYPE = 'http://uri.etsi.org/01903#SignedProperties';

    private const SIGNATURE_ID = 'Signature-1';

    private const SIGNED_PROPERTIES_ID = 'SignedProperties-1';

    private const MIN_RSA_BITS = 2048;

    /** Length of each of R and S for P-256. */
    private const P256_COORDINATE_LENGTH = 32;

    /** The signing time is backdated by this many seconds, so a slightly fast local clock is not refused. */
    private const CLOCK_SKEW_SECONDS = 60;

    /** @var Closure(): DateTimeInterface */
    private readonly Closure $now;

    /**
     * @param  ?Closure(): DateTimeInterface  $now  the clock for the signing time; the system clock by default
     */
    public function __construct(?Closure $now = null)
    {
        $this->now = $now ?? static fn (): DateTimeInterface => new DateTimeImmutable;
    }

    /**
     * @return string the signed document
     *
     * @throws InvalidArgumentException When the document cannot be parsed, or the key type or size is not supported
     * @throws RuntimeException When OpenSSL fails to sign
     */
    public function sign(string $xml, CertificateCredentials $credentials): string
    {
        $key = $credentials->privateKey();
        $isEc = self::isEllipticCurve($key);
        $document = self::parse($xml);
        $root = $document->documentElement ?? throw new InvalidArgumentException('The document has no root element.');

        $certificateDer = self::certificateDer($credentials->certificatePem);
        $certificate = openssl_x509_parse($credentials->certificatePem);
        if ($certificate === false) {
            throw new InvalidArgumentException('The certificate cannot be parsed.');
        }

        // The enveloped-signature transform removes the signature again, so the document digest
        // is the digest of the document as it is before the signature is added.
        $documentDigest = self::digest(self::canonicalize($root));

        $signature = self::ds($document, 'Signature');
        $signature->setAttribute('Id', self::SIGNATURE_ID);
        $root->appendChild($signature);

        $signedInfo = $signature->appendChild(self::ds($document, 'SignedInfo'));
        $signedInfo->appendChild(self::ds($document, 'CanonicalizationMethod'))->setAttribute('Algorithm', self::EXCLUSIVE_C14N);
        $signedInfo->appendChild(self::ds($document, 'SignatureMethod'))->setAttribute('Algorithm', $isEc ? self::ECDSA_SHA256 : self::RSA_SHA256);
        $signedInfo->appendChild(self::reference($document, '', null, [self::ENVELOPED_SIGNATURE, self::EXCLUSIVE_C14N], $documentDigest));

        $signatureValue = $signature->appendChild(self::ds($document, 'SignatureValue'));
        $x509Data = $signature->appendChild(self::ds($document, 'KeyInfo'))->appendChild(self::ds($document, 'X509Data'));
        $x509Data->appendChild(self::ds($document, 'X509Certificate', base64_encode($certificateDer)));

        $qualifyingProperties = $signature->appendChild(self::ds($document, 'Object'))->appendChild(self::xades($document, 'QualifyingProperties'));
        $qualifyingProperties->setAttribute('Target', '#'.self::SIGNATURE_ID);
        $signedProperties = $qualifyingProperties->appendChild(self::xades($document, 'SignedProperties'));
        $signedProperties->setAttribute('Id', self::SIGNED_PROPERTIES_ID);
        $signedProperties->setIdAttribute('Id', true);

        $signatureProperties = $signedProperties->appendChild(self::xades($document, 'SignedSignatureProperties'));
        $signatureProperties->appendChild(self::xades($document, 'SigningTime', $this->signingTime()));
        $cert = $signatureProperties->appendChild(self::xades($document, 'SigningCertificate'))->appendChild(self::xades($document, 'Cert'));
        $certDigest = $cert->appendChild(self::xades($document, 'CertDigest'));
        $certDigest->appendChild(self::ds($document, 'DigestMethod'))->setAttribute('Algorithm', self::SHA256);
        $certDigest->appendChild(self::ds($document, 'DigestValue', self::digest($certificateDer)));
        $issuerSerial = $cert->appendChild(self::xades($document, 'IssuerSerial'));
        $issuerSerial->appendChild(self::ds($document, 'X509IssuerName', self::issuerName(self::issuer($certificate))));
        $issuerSerial->appendChild(self::ds($document, 'X509SerialNumber', self::decimalSerial(self::serialHex($certificate))));

        $signedInfo->appendChild(self::reference(
            $document,
            '#'.self::SIGNED_PROPERTIES_ID,
            self::SIGNED_PROPERTIES_TYPE,
            [self::EXCLUSIVE_C14N],
            self::digest(self::canonicalize($signedProperties)),
        ));

        if (! openssl_sign(self::canonicalize($signedInfo), $value, $key, OPENSSL_ALGO_SHA256) || ! is_string($value)) {
            while (openssl_error_string() !== false) {
                // Drain the queue; the messages may describe the key.
            }

            throw new RuntimeException('OpenSSL could not sign the document.');
        }
        if ($isEc) {
            $value = self::derToRaw($value, self::P256_COORDINATE_LENGTH);
        }
        $signatureValue->appendChild($document->createTextNode(base64_encode($value)));

        return (string) $document->saveXML();
    }

    /**
     * The decimal form of a certificate serial number given in hexadecimal, as
     * openssl_x509_parse() returns it in "serialNumberHex". KSeF refuses a hexadecimal
     * X509SerialNumber.
     *
     * @internal
     *
     * @throws InvalidArgumentException When the value is not hexadecimal
     */
    public static function decimalSerial(string $hex): string
    {
        if (preg_match('/\A[0-9A-Fa-f]+\z/', $hex) !== 1) {
            throw new InvalidArgumentException('The certificate serial number is not hexadecimal.');
        }

        return (string) BigInteger::fromBase(strtolower($hex), 16);
    }

    /**
     * The RFC 4514 string of a distinguished name as openssl_x509_parse() returns it: the
     * attributes in reverse order, joined with ", ", special characters escaped. An attribute
     * that occurs more than once comes as a list, in certificate order.
     *
     * @internal
     *
     * @param  array<string, string|list<string>>  $name
     */
    public static function issuerName(array $name): string
    {
        $attributes = [];
        foreach ($name as $type => $values) {
            foreach ((array) $values as $value) {
                $attributes[] = $type.'='.self::escapeRdnValue($value);
            }
        }

        return implode(', ', array_reverse($attributes));
    }

    private static function escapeRdnValue(string $value): string
    {
        $trailing = '';
        if (str_ends_with($value, ' ')) {
            $value = substr($value, 0, -1);
            $trailing = '\\ ';
        }

        $escaped = str_replace("\0", '\\00', (string) preg_replace('/[\\\\"+,;<>]/', '\\\\$0', $value));
        if ($value !== '' && ($value[0] === '#' || $value[0] === ' ')) {
            $escaped = '\\'.$escaped;
        }

        return $escaped.$trailing;
    }

    /**
     * @throws InvalidArgumentException When the key is neither RSA of at least 2048 bits nor EC on P-256
     */
    private static function isEllipticCurve(OpenSSLAsymmetricKey $key): bool
    {
        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new InvalidArgumentException('The private key cannot be inspected.');
        }

        if ($details['type'] === OPENSSL_KEYTYPE_RSA) {
            $bits = is_int($details['bits'] ?? null) ? $details['bits'] : 0;
            if ($bits < self::MIN_RSA_BITS) {
                throw new InvalidArgumentException(sprintf('RSA keys must be at least %d bits long; this one has %d.', self::MIN_RSA_BITS, $bits));
            }

            return false;
        }

        if ($details['type'] === OPENSSL_KEYTYPE_EC) {
            $curve = is_array($details['ec'] ?? null) ? ($details['ec']['curve_name'] ?? null) : null;
            if ($curve !== 'prime256v1') {
                throw new InvalidArgumentException(sprintf('EC keys must be on the P-256 curve; this one is on %s.', is_string($curve) ? $curve : 'an unnamed curve'));
            }

            return true;
        }

        throw new InvalidArgumentException('Only RSA and EC (P-256) keys can sign a KSeF authentication request.');
    }

    /**
     * @throws InvalidArgumentException When the document is not well-formed or has a document type declaration
     */
    private static function parse(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $xml !== '' && $document->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new InvalidArgumentException('The document to sign is not well-formed XML.');
        }
        if ($document->doctype !== null) {
            throw new InvalidArgumentException('The document to sign must not have a document type declaration.');
        }

        return $document;
    }

    private function signingTime(): string
    {
        $now = DateTimeImmutable::createFromInterface(($this->now)());

        return $now->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('-%d seconds', self::CLOCK_SKEW_SECONDS))
            ->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @param  list<string>  $transforms
     */
    private static function reference(DOMDocument $document, string $uri, ?string $type, array $transforms, string $digest): DOMElement
    {
        $reference = self::ds($document, 'Reference');
        $reference->setAttribute('URI', $uri);
        if ($type !== null) {
            $reference->setAttribute('Type', $type);
        }
        $transformsElement = $reference->appendChild(self::ds($document, 'Transforms'));
        foreach ($transforms as $algorithm) {
            $transformsElement->appendChild(self::ds($document, 'Transform'))->setAttribute('Algorithm', $algorithm);
        }
        $reference->appendChild(self::ds($document, 'DigestMethod'))->setAttribute('Algorithm', self::SHA256);
        $reference->appendChild(self::ds($document, 'DigestValue', $digest));

        return $reference;
    }

    private static function ds(DOMDocument $document, string $name, ?string $text = null): DOMElement
    {
        return self::element($document, self::DS_NAMESPACE, 'ds:'.$name, $text);
    }

    private static function xades(DOMDocument $document, string $name, ?string $text = null): DOMElement
    {
        return self::element($document, self::XADES_NAMESPACE, 'xades:'.$name, $text);
    }

    private static function element(DOMDocument $document, string $namespace, string $qualifiedName, ?string $text): DOMElement
    {
        $element = $document->createElementNS($namespace, $qualifiedName);
        if ($text !== null) {
            // A text node, so that markup characters in an issuer name are escaped.
            $element->appendChild($document->createTextNode($text));
        }

        return $element;
    }

    /**
     * Exclusive C14N without comments, as the signature declares.
     */
    private static function canonicalize(DOMNode $node): string
    {
        $canonical = $node->C14N(true, false);
        if ($canonical === false) {
            throw new RuntimeException('The document could not be canonicalized.');
        }

        return $canonical;
    }

    private static function digest(string $data): string
    {
        return base64_encode(hash('sha256', $data, true));
    }

    private static function certificateDer(string $pem): string
    {
        if (! openssl_x509_export($pem, $normalized) || ! is_string($normalized)) {
            throw new InvalidArgumentException('The certificate cannot be read.');
        }
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $normalized) ?? '', true);
        if ($der === false || $der === '') {
            throw new InvalidArgumentException('The certificate cannot be read.');
        }

        return $der;
    }

    /**
     * @param  array<array-key, mixed>  $certificate
     * @return array<string, string|list<string>>
     */
    private static function issuer(array $certificate): array
    {
        $issuer = $certificate['issuer'] ?? null;
        if (! is_array($issuer)) {
            throw new InvalidArgumentException('The certificate has no issuer.');
        }

        $name = [];
        foreach ($issuer as $type => $values) {
            $name[(string) $type] = is_array($values)
                ? array_values(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $values))
                : (is_scalar($values) ? (string) $values : '');
        }

        return $name;
    }

    /**
     * @param  array<array-key, mixed>  $certificate
     */
    private static function serialHex(array $certificate): string
    {
        $hex = $certificate['serialNumberHex'] ?? null;
        if (! is_string($hex) || $hex === '') {
            throw new InvalidArgumentException('The certificate has no serial number.');
        }

        return $hex;
    }

    /**
     * Converts an OpenSSL DER-encoded ECDSA signature (SEQUENCE of two INTEGERs) to R||S, each
     * left-padded to the coordinate length.
     */
    private static function derToRaw(string $der, int $length): string
    {
        $offset = 0;
        if (self::byte($der, $offset++) !== 0x30) {
            throw new RuntimeException('The ECDSA signature is not a DER sequence.');
        }
        self::derLength($der, $offset);

        $raw = '';
        for ($i = 0; $i < 2; $i++) {
            if (self::byte($der, $offset++) !== 0x02) {
                throw new RuntimeException('The ECDSA signature does not hold two DER integers.');
            }
            $size = self::derLength($der, $offset);
            $integer = ltrim(substr($der, $offset, $size), "\x00");
            $offset += $size;
            if (strlen($integer) > $length) {
                throw new RuntimeException('The ECDSA signature component is longer than the curve allows.');
            }
            $raw .= str_pad($integer, $length, "\x00", STR_PAD_LEFT);
        }

        return $raw;
    }

    private static function derLength(string $der, int &$offset): int
    {
        $length = self::byte($der, $offset++);
        if ($length < 0x80) {
            return $length;
        }

        $bytes = $length & 0x7F;
        $length = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) | self::byte($der, $offset++);
        }

        return $length;
    }

    private static function byte(string $data, int $offset): int
    {
        if ($offset >= strlen($data)) {
            throw new RuntimeException('The ECDSA signature is truncated.');
        }

        return ord($data[$offset]);
    }
}
