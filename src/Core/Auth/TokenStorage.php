<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

/**
 * Implementação simples de armazenamento de tokens em memória
 *
 * Para produção, considere usar implementações persistentes
 * como arquivo, banco de dados ou cache.
 */
class TokenStorage implements TokenStorageInterface
{
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?int $accessTokenExpiration = null;

    public function storeAccessToken(string $token, int $expiresIn): void
    {
        $this->accessToken = $token;
        $this->accessTokenExpiration = time() + $expiresIn;
    }

    public function storeRefreshToken(string $token): void
    {
        $this->refreshToken = $token;
    }

    public function getAccessToken(): ?string
    {
        if ($this->isAccessTokenExpired()) {
            return null;
        }

        return $this->accessToken;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function hasValidAccessToken(): bool
    {
        return $this->accessToken !== null && !$this->isAccessTokenExpired();
    }

    public function hasRefreshToken(): bool
    {
        return $this->refreshToken !== null;
    }

    public function clear(): void
    {
        $this->accessToken = null;
        $this->refreshToken = null;
        $this->accessTokenExpiration = null;
    }

    public function getAccessTokenExpiration(): ?int
    {
        return $this->accessTokenExpiration;
    }

    public function isAccessTokenExpired(): bool
    {
        if ($this->accessTokenExpiration === null) {
            return true;
        }

        return time() >= $this->accessTokenExpiration;
    }

    public function willAccessTokenExpireIn(int $seconds): bool
    {
        if ($this->accessTokenExpiration === null) {
            return true;
        }

        return time() + $seconds >= $this->accessTokenExpiration;
    }
}