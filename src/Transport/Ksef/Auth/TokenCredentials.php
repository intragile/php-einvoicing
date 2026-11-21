<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use IntrAgile\EInvoicing\Transport\Http\Redactor;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

/**
 * A KSeF token, as generated in the KSeF web application or through the API.
 *
 * Surrounding whitespace, as left by a copied environment variable or file, is removed, and the
 * trimmed value is the one stored and sent.
 *
 * The token stays out of var_export(), print_r(), var_dump(), json_encode() and array-cast
 * output, and the object refuses to be serialized.
 */
final readonly class TokenCredentials implements Credentials
{
    private Secret $token;

    /**
     * @throws InvalidArgumentException When the token is blank
     */
    public function __construct(#[SensitiveParameter] string $token)
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('The KSeF token must not be blank.');
        }
        $this->token = new Secret($token);
    }

    /**
     * The token itself, for the request that carries it.
     *
     * @internal returns the raw KSeF token; for the library's own requests and redaction only
     */
    public function token(): string
    {
        return $this->token->reveal();
    }

    /**
     * @return array{token: string}
     */
    public function __debugInfo(): array
    {
        return ['token' => Redactor::REDACTED];
    }

    /**
     * @throws LogicException Always: a credential must not be written to a cache, queue or session
     */
    public function __serialize(): never
    {
        throw new LogicException('KSeF token credentials cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('KSeF token credentials cannot be serialized.');
    }
}
