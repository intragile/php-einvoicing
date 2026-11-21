<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use LogicException;
use SensitiveParameter;

/**
 * The tokens of an authenticated KSeF context: the short-lived access token sent as the bearer of
 * every protected request, and the refresh token that obtains a new access token without a new
 * authentication.
 *
 * Both tokens stay out of var_export(), print_r(), var_dump(), json_encode() and array-cast
 * output, and the object refuses to be serialized.
 */
final readonly class AccessTokens
{
    private Secret $accessToken;

    private Secret $refreshToken;

    public function __construct(
        #[SensitiveParameter] string $accessToken,
        public DateTimeImmutable $accessValidUntil,
        #[SensitiveParameter] string $refreshToken,
        public DateTimeImmutable $refreshValidUntil,
    ) {
        $this->accessToken = new Secret($accessToken);
        $this->refreshToken = new Secret($refreshToken);
    }

    public function accessToken(): string
    {
        return $this->accessToken->reveal();
    }

    public function refreshToken(): string
    {
        return $this->refreshToken->reveal();
    }

    /**
     * A copy with a new access token and the same refresh token, as after a refresh.
     */
    public function withAccessToken(#[SensitiveParameter] string $accessToken, DateTimeImmutable $validUntil): self
    {
        return new self($accessToken, $validUntil, $this->refreshToken->reveal(), $this->refreshValidUntil);
    }

    /**
     * @return array{accessToken: string, accessValidUntil: DateTimeImmutable, refreshToken: string, refreshValidUntil: DateTimeImmutable}
     */
    public function __debugInfo(): array
    {
        return [
            'accessToken' => Redactor::REDACTED,
            'accessValidUntil' => $this->accessValidUntil,
            'refreshToken' => Redactor::REDACTED,
            'refreshValidUntil' => $this->refreshValidUntil,
        ];
    }

    /**
     * @throws LogicException Always: a token must not be written to a cache, queue or session
     */
    public function __serialize(): never
    {
        throw new LogicException('KSeF access tokens cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('KSeF access tokens cannot be serialized.');
    }
}
