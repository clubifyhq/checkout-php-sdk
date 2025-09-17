<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

interface AuthManagerInterface
{
    /**
     * Autenticar com credenciais
     */
    public function authenticate(string $tenantId, string $apiKey): bool;

    /**
     * Verificar se está autenticado
     */
    public function isAuthenticated(): bool;

    /**
     * Obter token de acesso atual
     */
    public function getAccessToken(): ?string;

    /**
     * Obter token de refresh atual
     */
    public function getRefreshToken(): ?string;

    /**
     * Renovar token usando refresh token
     */
    public function refreshToken(): bool;

    /**
     * Fazer logout (limpar tokens)
     */
    public function logout(): void;

    /**
     * Obter header de autorização
     */
    public function getAuthorizationHeader(): array;

    /**
     * Verificar se token está expirado
     */
    public function isTokenExpired(): bool;

    /**
     * Obter informações do usuário autenticado
     */
    public function getUserInfo(): ?array;

    /**
     * Verificar se token será expirado em X segundos
     */
    public function willExpireIn(int $seconds): bool;
}
