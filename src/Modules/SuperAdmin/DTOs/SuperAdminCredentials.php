<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\SuperAdmin\DTOs;

use Clubify\Checkout\Contracts\ValidatableInterface;
use Clubify\Checkout\Exceptions\ValidationException;

/**
 * DTO para credenciais de super admin
 */
class SuperAdminCredentials implements ValidatableInterface
{
    public function __construct(
        public readonly string $apiKey,
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly ?string $username = null,
        public readonly ?string $password = null
    ) {
        $this->validate();
    }

    /**
     * Criar a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            apiKey: $data['api_key'] ?? '',
            accessToken: $data['access_token'] ?? null,
            refreshToken: $data['refresh_token'] ?? null,
            username: $data['username'] ?? null,
            password: $data['password'] ?? null
        );
    }

    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'username' => $this->username,
            'password' => $this->password
        ];
    }

    /**
     * Verificar se tem tokens completos
     */
    public function hasCompleteTokens(): bool
    {
        return !empty($this->accessToken) && !empty($this->refreshToken);
    }

    /**
     * Verificar se tem credenciais de usuário
     */
    public function hasUserCredentials(): bool
    {
        return !empty($this->username) && !empty($this->password);
    }

    /**
     * Validar credenciais
     */
    public function validate(): void
    {
        if (empty($this->apiKey)) {
            throw new ValidationException('API key is required for super admin credentials');
        }

        // Validar formato da API key
        if (!preg_match('/^clb_(test|live)_[a-f0-9]{32}$/', $this->apiKey)) {
            throw new ValidationException('Invalid super admin API key format');
        }

        // Se tem username, deve ter password também
        if (!empty($this->username) && empty($this->password)) {
            throw new ValidationException('Password is required when username is provided');
        }

        if (!empty($this->password) && empty($this->username)) {
            throw new ValidationException('Username is required when password is provided');
        }
    }
}