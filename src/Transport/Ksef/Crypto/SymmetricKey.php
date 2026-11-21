<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Crypto;

use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Secret;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

/**
 * An AES-256 key and its CBC initialization vector, as used for one KSeF session.
 *
 * The key is held in a Secret, so it stays out of var_export(), json_encode(), print_r(),
 * var_dump() and array casts of this object and of the objects that hold it; read it with key().
 */
final readonly class SymmetricKey
{
    public const KEY_LENGTH = 32;

    public const IV_LENGTH = 16;

    private Secret $key;

    /**
     * @param  string  $key  32 raw bytes
     * @param  string  $iv  16 raw bytes
     *
     * @throws InvalidArgumentException When a length is wrong
     */
    public function __construct(
        #[SensitiveParameter] string $key,
        public string $iv,
    ) {
        if (strlen($key) !== self::KEY_LENGTH) {
            throw new InvalidArgumentException(sprintf('The AES key must be %d bytes long.', self::KEY_LENGTH));
        }

        if (strlen($iv) !== self::IV_LENGTH) {
            throw new InvalidArgumentException(sprintf('The initialization vector must be %d bytes long.', self::IV_LENGTH));
        }
        $this->key = new Secret($key);
    }

    /**
     * The 32 raw key bytes.
     *
     * @internal for the library's own encryption only
     */
    public function key(): string
    {
        return $this->key->reveal();
    }

    /**
     * Generates a fresh key and initialization vector from a cryptographically secure source.
     */
    public static function generate(): self
    {
        return new self(random_bytes(self::KEY_LENGTH), random_bytes(self::IV_LENGTH));
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'key' => Redactor::REDACTED,
            'iv' => base64_encode($this->iv),
        ];
    }

    /**
     * @throws LogicException Always: a session key must not be written to a cache, queue or session
     */
    public function __serialize(): never
    {
        throw new LogicException('The KSeF symmetric key cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('The KSeF symmetric key cannot be serialized.');
    }
}
