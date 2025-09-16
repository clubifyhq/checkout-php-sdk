<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

interface TokenStorageInterface
{
    /**
     * Armazenar token de acesso
     */
    public function storeAccessToken(string $token, int $expiresIn): void;

    /**
     * Armazenar token de refresh
     */
    public function storeRefreshToken(string $token): void;

    /**
     * Obter token de acesso
     */
    public function getAccessToken(): ?string;

    /**
     * Obter token de refresh
     */
    public function getRefreshToken(): ?string;

    /**
     * Verificar se token de acesso existe e é válido
     */
    public function hasValidAccessToken(): bool;

    /**
     * Verificar se token de refresh existe
     */
    public function hasRefreshToken(): bool;

    /**
     * Limpar todos os tokens
     */
    public function clear(): void;

    /**
     * Obter timestamp de expiração do access token
     */
    public function getAccessTokenExpiration(): ?int;

    /**
     * Verificar se access token está expirado
     */
    public function isAccessTokenExpired(): bool;

    /**
     * Verificar se access token irá expirar em X segundos
     */
    public function willAccessTokenExpireIn(int $seconds): bool;
}