<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use IntrAgile\EInvoicing\Transport\Http\Redactor;
use LogicException;
use SensitiveParameter;

/**
 * A KSeF token just generated through the API, with the reference number that identifies it in
 * later status, approval and revocation calls.
 *
 * KSeF shows the token only once, in the answer to its generation. The token stays out of
 * var_export(), print_r(), var_dump(), json_encode() and array-cast output, and the object
 * refuses to be serialized.
 */
final readonly class GeneratedToken
{
    private Secret $token;

    public function __construct(
        public string $referenceNumber,
        #[SensitiveParameter] string $token,
    ) {
        $this->token = new Secret($token);
    }

    /**
     * The token itself, to be stored as a credential.
     */
    public function token(): string
    {
        return $this->token->reveal();
    }

    /**
     * @return array{referenceNumber: string, token: string}
     */
    public function __debugInfo(): array
    {
        return ['referenceNumber' => $this->referenceNumber, 'token' => Redactor::REDACTED];
    }

    /**
     * @throws LogicException Always: a credential must not be written to a cache, queue or session
     */
    public function __serialize(): never
    {
        throw new LogicException('A generated KSeF token cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('A generated KSeF token cannot be serialized.');
    }
}
