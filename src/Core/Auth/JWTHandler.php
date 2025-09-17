<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Handler para tokens JWT
 *
 * Fornece funcionalidades para decodificar e validar tokens JWT
 * usando a biblioteca firebase/php-jwt.
 */
class JWTHandler
{
    private string $algorithm;
    private ?string $publicKey;

    public function __construct(string $algorithm = 'HS256', ?string $publicKey = null)
    {
        $this->algorithm = $algorithm;
        $this->publicKey = $publicKey;
    }

    /**
     * Decodificar token JWT
     */
    public function decode(string $token, ?string $key = null): array
    {
        $decodeKey = $key ?? $this->publicKey;

        if (!$decodeKey) {
            throw new \InvalidArgumentException('JWT key is required for decoding');
        }

        try {
            $decoded = JWT::decode($token, new Key($decodeKey, $this->algorithm));
            return (array) $decoded;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid JWT token: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Verificar se token está expirado
     */
    public function isExpired(string $token, ?string $key = null): bool
    {
        try {
            $payload = $this->decode($token, $key);

            if (!isset($payload['exp'])) {
                return false; // Se não tem exp, consideramos como não expirado
            }

            return time() >= $payload['exp'];
        } catch (\Exception) {
            return true; // Se não consegue decodificar, consideramos expirado
        }
    }

    /**
     * Obter informações do payload do token
     */
    public function getPayload(string $token, ?string $key = null): array
    {
        return $this->decode($token, $key);
    }

    /**
     * Obter claim específico do token
     */
    public function getClaim(string $token, string $claim, ?string $key = null): mixed
    {
        $payload = $this->decode($token, $key);
        return $payload[$claim] ?? null;
    }

    /**
     * Verificar se token irá expirar em X segundos
     */
    public function willExpireIn(string $token, int $seconds, ?string $key = null): bool
    {
        try {
            $payload = $this->decode($token, $key);

            if (!isset($payload['exp'])) {
                return false;
            }

            return time() + $seconds >= $payload['exp'];
        } catch (\Exception) {
            return true;
        }
    }

    /**
     * Obter tempo restante até expiração (em segundos)
     */
    public function getTimeToExpiration(string $token, ?string $key = null): ?int
    {
        try {
            $payload = $this->decode($token, $key);

            if (!isset($payload['exp'])) {
                return null;
            }

            $timeLeft = $payload['exp'] - time();
            return max(0, $timeLeft);
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Validar estrutura básica do token
     */
    public function validateStructure(string $token): bool
    {
        $parts = explode('.', $token);
        return count($parts) === 3;
    }

    /**
     * Obter header do token (sem verificação de assinatura)
     */
    public function getHeader(string $token): array
    {
        if (!$this->validateStructure($token)) {
            throw new \InvalidArgumentException('Invalid JWT structure');
        }

        $parts = explode('.', $token);
        $header = base64_decode($parts[0]);

        $decoded = json_decode($header, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JWT header');
        }

        return $decoded;
    }

    /**
     * Obter payload do token (sem verificação de assinatura)
     */
    public function getUnsafePayload(string $token): array
    {
        if (!$this->validateStructure($token)) {
            throw new \InvalidArgumentException('Invalid JWT structure');
        }

        $parts = explode('.', $token);
        $payload = base64_decode($parts[1]);

        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JWT payload');
        }

        return $decoded;
    }
}
