<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

use DateTimeImmutable;
use DateTimeZone;
use DOMElement;
use DOMXPath;
use Exception;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Reads a participant's ServiceGroup and ServiceMetadata from a Peppol SMP (SMP 1.x REST).
 *
 * Each path segment is percent-encoded on its own (RFC 3986), so "::" becomes "%3A%3A" and "#"
 * becomes "%23". Responses are parsed with SafeXml and matched by namespace, whatever the
 * prefixes. One ServiceMetadata Redirect is followed; a redirect to another redirect is refused.
 * The XMLDSig signature of the ServiceMetadata is not verified.
 *
 * Responses larger than the size cap (2 MiB by default) are refused while they are read: the cap
 * is passed to the HttpClient for each request, so an oversized body is not read to the end.
 * The DocumentIdentifier of a ServiceMetadata answer must repeat the requested document type:
 * the scheme in any case, as Peppol identifier schemes are case-insensitive, and the value
 * exactly; an answer for another document type is refused.
 */
final class SmpClient
{
    private const NS_SMP = 'http://busdox.org/serviceMetadata/publishing/1.0/';

    private const NS_IDS = 'http://busdox.org/transport/identifiers/1.0/';

    private const NS_WSA = 'http://www.w3.org/2005/08/addressing';

    private const DATE_TIME = '/\A\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})?\z/';

    /** Default response size cap: 2 MiB. */
    public const DEFAULT_MAX_BYTES = 2_097_152;

    public function __construct(
        private readonly HttpClient $http,
        private readonly int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {
        if ($maxBytes < 1) {
            throw new InvalidArgumentException('The SMP response size cap must be at least one byte.');
        }
    }

    /**
     * The document types the participant's ServiceGroup references; empty when the SMP does not
     * know the participant (404).
     *
     * @return list<string> document type identifiers, without scheme for busdox-docid-qns
     *
     * @throws TransportUnavailable when the SMP cannot be reached or answers with a 5xx status
     * @throws UnexpectedResponse when the answer is oversized, malformed or has another status
     * @throws UnsafeXml when the answer carries a DOCTYPE declaration
     */
    public function documentTypes(string $smpUrl, ParticipantId $id): array
    {
        return $this->findDocumentTypes($smpUrl, $id) ?? [];
    }

    /**
     * Like documentTypes(), but null when the SMP does not know the participant (404), so that an
     * empty ServiceGroup can be told from a missing one.
     *
     * @return list<string>|null
     *
     * @throws TransportUnavailable when the SMP cannot be reached or answers with a 5xx status
     * @throws UnexpectedResponse when the answer is oversized, malformed or has another status
     * @throws UnsafeXml when the answer carries a DOCTYPE declaration
     */
    public function findDocumentTypes(string $smpUrl, ParticipantId $id): ?array
    {
        $fetched = $this->fetch(self::serviceGroupUrl($smpUrl, $id));
        if ($fetched === null) {
            return null;
        }
        [$response, $xpath, $root] = $fetched;
        self::expectRoot($response, $root, 'ServiceGroup');

        $types = [];
        foreach ($xpath->query('smp:ServiceMetadataReferenceCollection/smp:ServiceMetadataReference/@href', $root) ?: [] as $href) {
            $type = self::documentTypeFromHref((string) $href->nodeValue);
            if ($type !== null && ! in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * The endpoints the SMP publishes for the participant and document type; empty when the SMP
     * does not know them (404).
     *
     * @return list<ServiceEndpoint>
     *
     * @throws TransportUnavailable when the SMP cannot be reached or answers with a 5xx status
     * @throws UnexpectedResponse when the answer is oversized, malformed, a second redirect, a
     *                            redirect to a refused URL, for another document type or has
     *                            another status
     * @throws UnsafeXml when the answer carries a DOCTYPE declaration
     */
    public function endpoints(string $smpUrl, ParticipantId $id, DocumentTypeId $type): array
    {
        return $this->findEndpoints($smpUrl, $id, $type) ?? [];
    }

    /**
     * Like endpoints(), but null when the SMP does not know the participant or document type (404).
     *
     * @return list<ServiceEndpoint>|null
     *
     * @throws TransportUnavailable when the SMP cannot be reached or answers with a 5xx status
     * @throws UnexpectedResponse when the answer is oversized, malformed, a second redirect, a
     *                            redirect to a refused URL, for another document type or has
     *                            another status
     * @throws UnsafeXml when the answer carries a DOCTYPE declaration
     */
    public function findEndpoints(string $smpUrl, ParticipantId $id, DocumentTypeId $type): ?array
    {
        $url = self::serviceGroupUrl($smpUrl, $id).'/services/'.rawurlencode($type->toUri());

        for ($redirects = 0; ; $redirects++) {
            $fetched = $this->fetch($url);
            if ($fetched === null) {
                return null;
            }
            [$response, $xpath, $root] = $fetched;
            $metadata = self::serviceMetadata($response, $xpath, $root);

            $redirect = self::first($xpath, 'smp:Redirect', $metadata);
            if ($redirect === null) {
                return self::parseEndpoints($response, $xpath, $metadata, $type);
            }
            if ($redirects > 0) {
                throw UnexpectedResponse::forResponse($response, 'The SMP redirect leads to another redirect, which is refused');
            }
            $url = $redirect->getAttribute('href');
            $reason = SmpUrl::rejectionReason($url);
            if ($reason !== null) {
                throw UnexpectedResponse::forResponse($response, 'The SMP redirect target is refused: '.$reason);
            }
        }
    }

    private static function serviceGroupUrl(string $smpUrl, ParticipantId $id): string
    {
        return rtrim($smpUrl, '/').'/'.rawurlencode($id->toUri());
    }

    /**
     * @return array{ResponseInterface, DOMXPath, DOMElement}|null null on 404
     */
    private function fetch(string $url): ?array
    {
        $response = $this->http->send('GET', $url, maxBytes: $this->maxBytes);
        $status = $response->getStatusCode();
        if ($status === 404) {
            return null;
        }
        if ($status >= 500) {
            throw new TransportUnavailable(sprintf('The SMP at %s answered HTTP %d.', $url, $status));
        }
        if ($status !== 200) {
            throw UnexpectedResponse::forResponse($response, sprintf('The SMP at %s answered an unexpected status', $url));
        }

        $body = (string) $response->getBody();

        try {
            $document = SafeXml::load($body);
        } catch (UnsupportedDocument $exception) {
            throw UnexpectedResponse::forResponse($response, 'The SMP response is not usable XML: '.$exception->getMessage(), $body, $exception);
        }
        $root = $document->documentElement ?? throw UnexpectedResponse::forResponse($response, 'The SMP response has no root element', $body);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('smp', self::NS_SMP);
        $xpath->registerNamespace('ids', self::NS_IDS);
        $xpath->registerNamespace('wsa', self::NS_WSA);

        return [$response, $xpath, $root];
    }

    private static function expectRoot(ResponseInterface $response, DOMElement $root, string $localName): void
    {
        if ($root->namespaceURI !== self::NS_SMP || $root->localName !== $localName) {
            throw UnexpectedResponse::forResponse($response, sprintf(
                'The SMP response root is {%s}%s, not the SMP %s',
                (string) $root->namespaceURI,
                (string) $root->localName,
                $localName,
            ));
        }
    }

    /**
     * The ServiceMetadata element: the root itself or the child of a SignedServiceMetadata root.
     */
    private static function serviceMetadata(ResponseInterface $response, DOMXPath $xpath, DOMElement $root): DOMElement
    {
        if ($root->namespaceURI === self::NS_SMP && $root->localName === 'ServiceMetadata') {
            return $root;
        }
        self::expectRoot($response, $root, 'SignedServiceMetadata');

        return self::first($xpath, 'smp:ServiceMetadata', $root)
            ?? throw UnexpectedResponse::forResponse($response, 'The SMP SignedServiceMetadata has no ServiceMetadata');
    }

    /**
     * @return list<ServiceEndpoint>
     *
     * @throws UnexpectedResponse when the ServiceInformation is missing, incomplete or for another
     *                            document type than the one requested
     */
    private static function parseEndpoints(ResponseInterface $response, DOMXPath $xpath, DOMElement $metadata, DocumentTypeId $requested): array
    {
        $information = self::first($xpath, 'smp:ServiceInformation', $metadata)
            ?? throw UnexpectedResponse::forResponse($response, 'The SMP ServiceMetadata has neither ServiceInformation nor Redirect');
        $identifier = self::first($xpath, 'ids:DocumentIdentifier', $information)
            ?? throw UnexpectedResponse::forResponse($response, 'The SMP ServiceInformation has no DocumentIdentifier');
        $scheme = $identifier->getAttribute('scheme');
        $value = trim($identifier->textContent);
        // Identifier schemes are case-insensitive under the Peppol Policy for use of Identifiers;
        // the identifier value is compared exactly.
        if (strcasecmp($scheme, DocumentTypeId::SCHEME) !== 0 || $value !== $requested->value) {
            throw UnexpectedResponse::forResponse($response, sprintf(
                'The SMP DocumentIdentifier %s::%s does not match the requested document type %s',
                addcslashes($scheme, "\0..\37\177"),
                addcslashes($value, "\0..\37\177"),
                $requested->toUri(),
            ));
        }
        $documentType = $value;

        $endpoints = [];
        foreach (self::elements($xpath, 'smp:ProcessList/smp:Process', $information) as $process) {
            $processId = self::first($xpath, 'ids:ProcessIdentifier', $process);
            $processValue = $processId === null ? '' : trim($processId->textContent);
            foreach (self::elements($xpath, 'smp:ServiceEndpointList/smp:Endpoint', $process) as $endpoint) {
                $profile = $endpoint->getAttribute('transportProfile');
                $address = trim(self::text($xpath, 'wsa:EndpointReference/wsa:Address', $endpoint) ?? '');
                if ($profile === '' || $address === '') {
                    throw UnexpectedResponse::forResponse($response, 'An SMP Endpoint lacks its transportProfile or address');
                }
                $certificate = self::text($xpath, 'smp:Certificate', $endpoint);

                $endpoints[] = new ServiceEndpoint(
                    $documentType,
                    $processValue,
                    $profile,
                    $address,
                    $certificate === null ? null : (string) preg_replace('/\s+/', '', $certificate),
                    self::date($response, self::text($xpath, 'smp:ServiceActivationDate', $endpoint)),
                    self::date($response, self::text($xpath, 'smp:ServiceExpirationDate', $endpoint)),
                    self::nonEmpty(self::text($xpath, 'smp:TechnicalContactUrl', $endpoint)),
                );
            }
        }

        return $endpoints;
    }

    /**
     * The document type in the last path segment of a ServiceMetadataReference href.
     *
     * The path is cut out of the href by hand rather than with parse_url(): document type
     * identifiers contain "#", which some SMPs leave unencoded and parse_url() would take for the
     * start of a fragment. The query string, if any, is dropped; "#" is kept as part of the path.
     */
    private static function documentTypeFromHref(string $href): ?string
    {
        $path = trim($href);
        $authority = strpos($path, '://');
        if ($authority !== false) {
            $slash = strpos($path, '/', $authority + 3);
            $path = $slash === false ? '' : substr($path, $slash);
        }
        $query = strpos($path, '?');
        if ($query !== false) {
            $path = substr($path, 0, $query);
        }
        $segment = rawurldecode(substr($path, (int) strrpos($path, '/') + 1));
        $prefix = DocumentTypeId::SCHEME.'::';
        // Identifier schemes are case-insensitive under the Peppol Policy for use of Identifiers.
        if (strncasecmp($segment, $prefix, strlen($prefix)) === 0) {
            $segment = substr($segment, strlen($prefix));
        }

        return $segment === '' ? null : $segment;
    }

    /**
     * @throws UnexpectedResponse when the value is not an xs:date or xs:dateTime
     */
    private static function date(ResponseInterface $response, ?string $value): ?DateTimeImmutable
    {
        $value = self::nonEmpty($value);
        if ($value === null) {
            return null;
        }
        if (preg_match(self::DATE_TIME, $value) === 1) {
            try {
                return new DateTimeImmutable($value, new DateTimeZone('UTC'));
            } catch (Exception) {
                // Reported below.
            }
        }

        throw UnexpectedResponse::forResponse($response, sprintf('The SMP date "%s" is not a valid xs:dateTime', addcslashes($value, "\0..\37\177")));
    }

    private static function nonEmpty(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private static function text(DOMXPath $xpath, string $expression, DOMElement $context): ?string
    {
        return self::first($xpath, $expression, $context)?->textContent;
    }

    private static function first(DOMXPath $xpath, string $expression, DOMElement $context): ?DOMElement
    {
        return self::elements($xpath, $expression, $context)[0] ?? null;
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(DOMXPath $xpath, string $expression, DOMElement $context): array
    {
        $elements = [];
        foreach ($xpath->query($expression, $context) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
