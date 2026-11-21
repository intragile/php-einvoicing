<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\PublicKeyId;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefErrors;
use InvalidArgumentException;

/**
 * The Ministry of Finance public keys that client-side encryption uses, read from
 * GET /security/public-key-certificates (a list of PublicKeyCertificate: certificate as Base64
 * DER, certificateId, publicKeyId, validFrom, validTo and usage).
 *
 * The list is fetched once per instance, on first use. For each usage the key valid now with the
 * latest validFrom is chosen. The list is fetched again when the chosen key's validTo has passed,
 * after invalidate(), and by using() once after KSeF reports error 21470 (unknown or withdrawn key
 * id), as happens after a key rotation.
 */
final class PublicKeyRegistry
{
    /** The KSeF error code of an unknown or withdrawn public key id. */
    public const UNKNOWN_KEY_ID = 21470;

    private const OPERATION = 'The KSeF request GET /security/public-key-certificates';

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    /** @var list<array{pem: string, keyId: string, validFrom: DateTimeImmutable, validTo: DateTimeImmutable, usage: list<string>}>|null */
    private ?array $keys = null;

    /** @var array<string, array{pem: string, keyId: string, validFrom: DateTimeImmutable, validTo: DateTimeImmutable, usage: list<string>}> */
    private array $chosen = [];

    /**
     * @param  KsefApi  $api  the key list needs no authentication
     * @param  (Closure(): DateTimeImmutable)|null  $now  the current time; the system clock when null
     */
    public function __construct(private readonly KsefApi $api, ?Closure $now = null)
    {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable;
    }

    /**
     * The X.509 certificate of the key for the usage, PEM encoded.
     *
     * @throws TransportException when the list cannot be read or holds no valid key for the usage
     */
    public function certificatePem(PublicKeyUsage $usage): string
    {
        return $this->key($usage)['pem'];
    }

    /**
     * The publicKeyId of the key for the usage, to send alongside data encrypted with it.
     *
     * @throws TransportException when the list cannot be read or holds no valid key for the usage
     */
    public function keyId(PublicKeyUsage $usage): string
    {
        return $this->key($usage)['keyId'];
    }

    /**
     * Forgets the fetched list, so the next use fetches it again.
     */
    public function invalidate(): void
    {
        $this->keys = null;
        $this->chosen = [];
    }

    /**
     * Runs a call that encrypts with the key for the usage and sends its key id. When KSeF answers
     * with error 21470 (unknown key id), the list is fetched again and the call repeated once with
     * the then chosen key; a second 21470 is thrown as UnexpectedResponse naming the key id.
     *
     * The call must encrypt with the certificate it is given, so that a repeat uses the new key.
     *
     * @template T
     *
     * @param  Closure(string $certificatePem, string $keyId): T  $call
     * @return T
     *
     * @throws TransportException
     */
    public function using(PublicKeyUsage $usage, Closure $call): mixed
    {
        try {
            return $call($this->certificatePem($usage), $this->keyId($usage));
        } catch (TransportException $exception) {
            if (! KsefErrors::hasCode($exception, self::UNKNOWN_KEY_ID)) {
                throw $exception;
            }
        }

        $this->invalidate();
        $keyId = $this->keyId($usage);
        try {
            return $call($this->certificatePem($usage), $keyId);
        } catch (TransportException $exception) {
            if (! KsefErrors::hasCode($exception, self::UNKNOWN_KEY_ID)) {
                throw $exception;
            }

            throw new UnexpectedResponse(
                sprintf(
                    'KSeF refused the %s public key id "%s" with error %d (unknown or withdrawn key) also after the key list was fetched again.',
                    $usage->value,
                    $keyId,
                    self::UNKNOWN_KEY_ID,
                ),
                $exception instanceof UnexpectedResponse ? $exception->status : 0,
                $exception instanceof UnexpectedResponse ? $exception->bodyExcerpt : '',
                $exception,
            );
        }
    }

    /**
     * @return array{pem: string, keyId: string, validFrom: DateTimeImmutable, validTo: DateTimeImmutable, usage: list<string>}
     *
     * @throws TransportException
     */
    private function key(PublicKeyUsage $usage): array
    {
        $now = ($this->now)();
        $chosen = $this->chosen[$usage->value] ?? null;
        if ($chosen !== null && $now < $chosen['validTo']) {
            return $chosen;
        }
        if ($chosen !== null) {
            // The chosen key has expired: a successor may have been published since the fetch.
            $this->invalidate();
        }

        $this->keys ??= $this->fetch();
        $best = null;
        foreach ($this->keys as $key) {
            if (! in_array($usage->value, $key['usage'], true) || $now < $key['validFrom'] || $now >= $key['validTo']) {
                continue;
            }
            if ($best === null || $key['validFrom'] > $best['validFrom']) {
                $best = $key;
            }
        }
        if ($best === null) {
            throw new UnexpectedResponse(sprintf('KSeF published no public key for %s that is valid now.', $usage->value));
        }

        return $this->chosen[$usage->value] = $best;
    }

    /**
     * @return list<array{pem: string, keyId: string, validFrom: DateTimeImmutable, validTo: DateTimeImmutable, usage: list<string>}>
     *
     * @throws TransportException
     */
    private function fetch(): array
    {
        $keys = [];
        foreach ($this->api->json('GET', '/security/public-key-certificates') as $index => $entry) {
            if (! is_array($entry)) {
                throw new UnexpectedResponse(sprintf('%s was answered with entry %s that is not an object.', self::OPERATION, $index));
            }
            $der = base64_decode(KsefAnswer::string($entry, ['certificate'], self::OPERATION), true);
            if ($der === false || $der === '') {
                throw new UnexpectedResponse(sprintf('%s was answered with a certificate that is not Base64.', self::OPERATION));
            }
            $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
            $keyId = $entry['publicKeyId'] ?? null;
            if (! is_string($keyId) || $keyId === '') {
                try {
                    $keyId = PublicKeyId::of($pem);
                } catch (InvalidArgumentException $exception) {
                    throw new UnexpectedResponse(sprintf('%s was answered with a certificate whose public key cannot be read.', self::OPERATION), 0, '', $exception);
                }
            }
            $usage = $entry['usage'] ?? null;
            $keys[] = [
                'pem' => $pem,
                'keyId' => $keyId,
                'validFrom' => KsefAnswer::dateTime($entry, ['validFrom'], self::OPERATION),
                'validTo' => KsefAnswer::dateTime($entry, ['validTo'], self::OPERATION),
                // Usages this client does not know are kept but never match.
                'usage' => is_array($usage) ? array_values(array_filter($usage, is_string(...))) : [],
            ];
        }

        return $keys;
    }
}
