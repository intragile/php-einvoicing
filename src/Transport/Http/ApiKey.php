<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Http;

use Closure;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * An API key, kept inside a closure so that it stays out of var_export(), print_r(), var_dump()
 * and json_encode() output. Surrounding whitespace, as left by a copied environment variable, is
 * removed before the length check, and the trimmed value is the one stored.
 */
final class ApiKey
{
    /** Shortest accepted key; providers issue much longer ones. */
    public const MIN_LENGTH = 8;

    /** @var Closure(): string */
    private readonly Closure $value;

    /**
     * @param  string  $label  what the key is, for messages, for example "B2Brouter API key"
     *
     * @throws InvalidArgumentException when the trimmed key is shorter than eight characters
     */
    public function __construct(
        #[SensitiveParameter] string $value,
        public readonly string $label,
    ) {
        $value = trim($value);
        // The value is never quoted: even a wrong one may be a real credential.
        if (strlen($value) < self::MIN_LENGTH) {
            throw new InvalidArgumentException(sprintf('The %s must be at least %d characters.', $label, self::MIN_LENGTH));
        }
        $this->value = static fn (): string => $value;
    }

    /**
     * The key itself, for the request that carries it.
     */
    public function reveal(): string
    {
        return ($this->value)();
    }

    /**
     * @return array{value: string, label: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => Redactor::REDACTED, 'label' => $this->label];
    }
}
