<?php

declare(strict_types=1);

namespace Clubify\Checkout\Utils\Crypto;

use InvalidArgumentException;
use RuntimeException;

/**
 * Implementação de assinatura HMAC
 *
 * Fornece assinatura e verificação de dados usando HMAC
 * (Hash-based Message Authentication Code). Essencial para
 * validação de integridade e autenticidade de webhooks,
 * APIs e comunicação entre serviços.
 *
 * Funcionalidades:
 * - Assinatura HMAC-SHA256/SHA512
 * - Verificação de assinaturas
 * - Proteção contra timing attacks
 * - Suporte a múltiplos algoritmos hash
 * - Validação de webhook signatures
 * - Geração de tokens seguros
 *
 * Casos de uso:
 * - Validação de webhooks
 * - Autenticação de API
 * - Integridade de dados
 * - Tokens de sessão
 * - Assinatura de URLs
 *
 * Compliance de Segurança:
 * - RFC 2104 compliant
 * - Timing attack resistant
 * - OWASP guidelines
 * - PCI DSS compatible
 */
class HMACSignature
{
    private const DEFAULT_ALGORITHM = 'sha256';
    private const MIN_KEY_LENGTH = 16;
    private const MAX_KEY_LENGTH = 512;

    /**
     * Algoritmos suportados
     */
    private array $supportedAlgorithms = [
        'sha1' => ['length' => 20, 'secure' => false],
        'sha256' => ['length' => 32, 'secure' => true],
        'sha384' => ['length' => 48, 'secure' => true],
        'sha512' => ['length' => 64, 'secure' => true],
        'md5' => ['length' => 16, 'secure' => false], // Apenas para compatibilidade legacy
    ];

    /**
     * Configurações padrão
     */
    private array $defaultOptions = [
        'algorithm' => self::DEFAULT_ALGORITHM,
        'encoding' => 'hex',
        'prefix' => '',
        'case_sensitive' => true,
        'validate_key' => true,
    ];

    /**
     * Gera assinatura HMAC
     *
     * @param string $data Dados a serem assinados
     * @param string $key Chave secreta
     * @param array $options Opções de configuração
     * @return string Assinatura HMAC
     */
    public function sign(string $data, string $key, array $options = []): string
    {
        $options = array_merge($this->defaultOptions, $options);

        // Validações
        $this->validateSignInput($data, $key, $options);

        // Prepara chave
        $binaryKey = $this->prepareKey($key, $options);

        // Gera HMAC
        $hmac = hash_hmac($options['algorithm'], $data, $binaryKey, true);

        if ($hmac === false) {
            throw new RuntimeException('Falha ao gerar HMAC');
        }

        // Aplica encoding
        $encoded = $this->encodeSignature($hmac, $options['encoding']);

        // Adiciona prefixo se especificado
        return $options['prefix'] . $encoded;
    }

    /**
     * Verifica assinatura HMAC
     *
     * @param string $data Dados originais
     * @param string $signature Assinatura a ser verificada
     * @param string $key Chave secreta
     * @param array $options Opções de configuração
     * @return bool True se assinatura é válida
     */
    public function verify(string $data, string $signature, string $key, array $options = []): bool
    {
        $options = array_merge($this->defaultOptions, $options);

        try {
            // Validações
            $this->validateVerifyInput($data, $signature, $key, $options);

            // Remove prefixo se especificado
            if (!empty($options['prefix'])) {
                if (!str_starts_with($signature, $options['prefix'])) {
                    return false;
                }
                $signature = substr($signature, strlen($options['prefix']));
            }

            // Gera assinatura esperada
            $expectedSignature = $this->sign($data, $key, array_merge($options, ['prefix' => '']));

            // Comparação resistente a timing attacks
            return $this->secureCompare($signature, $expectedSignature, $options['case_sensitive']);

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gera assinatura para webhook
     *
     * @param string $payload Payload do webhook
     * @param string $secret Segredo do webhook
     * @param string $algorithm Algoritmo hash
     * @return string Assinatura formatada para webhook
     */
    public function signWebhook(string $payload, string $secret, string $algorithm = 'sha256'): string
    {
        $options = [
            'algorithm' => $algorithm,
            'encoding' => 'hex',
            'prefix' => $algorithm . '=',
        ];

        return $this->sign($payload, $secret, $options);
    }

    /**
     * Verifica assinatura de webhook
     *
     * @param string $payload Payload do webhook
     * @param string $signature Assinatura do header
     * @param string $secret Segredo do webhook
     * @return bool True se assinatura é válida
     */
    public function verifyWebhook(string $payload, string $signature, string $secret): bool
    {
        // Extrai algoritmo da assinatura (formato: sha256=abc123)
        if (!preg_match('/^([a-z0-9]+)=([a-f0-9]+)$/i', $signature, $matches)) {
            return false;
        }

        $algorithm = strtolower($matches[1]);
        $signatureValue = $matches[2];

        if (!isset($this->supportedAlgorithms[$algorithm])) {
            return false;
        }

        $options = [
            'algorithm' => $algorithm,
            'encoding' => 'hex',
            'prefix' => '',
        ];

        return $this->verify($payload, $signatureValue, $secret, $options);
    }

    /**
     * Gera múltiplas assinaturas (para diferentes algoritmos)
     *
     * @param string $data Dados a serem assinados
     * @param string $key Chave secreta
     * @param array $algorithms Lista de algoritmos
     * @return array Assinaturas por algoritmo
     */
    public function signMultiple(string $data, string $key, array $algorithms = ['sha256', 'sha512']): array
    {
        $signatures = [];

        foreach ($algorithms as $algorithm) {
            if (!isset($this->supportedAlgorithms[$algorithm])) {
                throw new InvalidArgumentException("Algoritmo não suportado: {$algorithm}");
            }

            $signatures[$algorithm] = $this->sign($data, $key, ['algorithm' => $algorithm]);
        }

        return $signatures;
    }

    /**
     * Verifica múltiplas assinaturas
     *
     * @param string $data Dados originais
     * @param array $signatures Assinaturas por algoritmo
     * @param string $key Chave secreta
     * @return array Resultado da verificação por algoritmo
     */
    public function verifyMultiple(string $data, array $signatures, string $key): array
    {
        $results = [];

        foreach ($signatures as $algorithm => $signature) {
            $results[$algorithm] = $this->verify($data, $signature, $key, ['algorithm' => $algorithm]);
        }

        return $results;
    }

    /**
     * Gera token seguro baseado em timestamp
     *
     * @param string $data Dados do token
     * @param string $key Chave secreta
     * @param int $ttl Tempo de vida em segundos
     * @return string Token com timestamp
     */
    public function generateTimestampToken(string $data, string $key, int $ttl = 3600): string
    {
        $timestamp = time() + $ttl;
        $payload = $data . '|' . $timestamp;
        $signature = $this->sign($payload, $key);

        return base64_encode($payload . '|' . $signature);
    }

    /**
     * Verifica token com timestamp
     *
     * @param string $token Token a ser verificado
     * @param string $key Chave secreta
     * @return array|null Dados do token ou null se inválido
     */
    public function verifyTimestampToken(string $token, string $key): ?array
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return null;
        }

        [$data, $timestamp, $signature] = $parts;

        // Verifica se token não expirou
        if (time() > (int) $timestamp) {
            return null;
        }

        // Verifica assinatura
        $payload = $data . '|' . $timestamp;
        if (!$this->verify($payload, $signature, $key)) {
            return null;
        }

        return [
            'data' => $data,
            'expires_at' => (int) $timestamp,
            'is_valid' => true,
        ];
    }

    /**
     * Gera chave HMAC segura
     *
     * @param int $length Comprimento da chave em bytes
     * @return string Chave gerada (base64 encoded)
     */
    public function generateKey(int $length = 32): string
    {
        if ($length < self::MIN_KEY_LENGTH || $length > self::MAX_KEY_LENGTH) {
            throw new InvalidArgumentException("Comprimento da chave deve estar entre " . self::MIN_KEY_LENGTH . " e " . self::MAX_KEY_LENGTH . " bytes");
        }

        $key = random_bytes($length);
        return base64_encode($key);
    }

    /**
     * Obtém informações sobre algoritmo
     *
     * @param string $algorithm Nome do algoritmo
     * @return array Informações do algoritmo
     */
    public function getAlgorithmInfo(string $algorithm = self::DEFAULT_ALGORITHM): array
    {
        if (!isset($this->supportedAlgorithms[$algorithm])) {
            throw new InvalidArgumentException("Algoritmo não suportado: {$algorithm}");
        }

        return [
            'algorithm' => $algorithm,
            'hash_length' => $this->supportedAlgorithms[$algorithm]['length'],
            'is_secure' => $this->supportedAlgorithms[$algorithm]['secure'],
            'is_recommended' => in_array($algorithm, ['sha256', 'sha512']),
            'use_cases' => $this->getAlgorithmUseCases($algorithm),
        ];
    }

    /**
     * Lista algoritmos suportados
     *
     * @param bool $secureOnly Retornar apenas algoritmos seguros
     * @return array Lista de algoritmos
     */
    public function getSupportedAlgorithms(bool $secureOnly = false): array
    {
        if (!$secureOnly) {
            return array_keys($this->supportedAlgorithms);
        }

        return array_keys(array_filter(
            $this->supportedAlgorithms,
            fn($info) => $info['secure']
        ));
    }

    /**
     * Valida entrada para assinatura
     */
    private function validateSignInput(string $data, string $key, array $options): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Chave HMAC não pode estar vazia');
        }

        if ($options['validate_key']) {
            $this->validateKey($key);
        }

        if (!isset($this->supportedAlgorithms[$options['algorithm']])) {
            throw new InvalidArgumentException("Algoritmo não suportado: {$options['algorithm']}");
        }

        if (!in_array($options['encoding'], ['hex', 'base64', 'raw'])) {
            throw new InvalidArgumentException("Encoding não suportado: {$options['encoding']}");
        }
    }

    /**
     * Valida entrada para verificação
     */
    private function validateVerifyInput(string $data, string $signature, string $key, array $options): void
    {
        if (empty($signature)) {
            throw new InvalidArgumentException('Assinatura não pode estar vazia');
        }

        $this->validateSignInput($data, $key, $options);
    }

    /**
     * Valida chave HMAC
     */
    private function validateKey(string $key): void
    {
        // Tenta decodificar como base64
        $decoded = base64_decode($key, true);
        if ($decoded !== false) {
            $length = strlen($decoded);
        } else {
            $length = strlen($key);
        }

        if ($length < self::MIN_KEY_LENGTH) {
            throw new InvalidArgumentException('Chave HMAC muito curta (mínimo ' . self::MIN_KEY_LENGTH . ' bytes)');
        }

        if ($length > self::MAX_KEY_LENGTH) {
            throw new InvalidArgumentException('Chave HMAC muito longa (máximo ' . self::MAX_KEY_LENGTH . ' bytes)');
        }
    }

    /**
     * Prepara chave para uso
     */
    private function prepareKey(string $key, array $options): string
    {
        // Tenta decodificar como base64
        $decoded = base64_decode($key, true);
        if ($decoded !== false && strlen($decoded) >= self::MIN_KEY_LENGTH) {
            return $decoded;
        }

        // Usa como chave raw
        return $key;
    }

    /**
     * Codifica assinatura conforme especificado
     */
    private function encodeSignature(string $hmac, string $encoding): string
    {
        switch ($encoding) {
            case 'hex':
                return bin2hex($hmac);
            case 'base64':
                return base64_encode($hmac);
            case 'raw':
                return $hmac;
            default:
                throw new InvalidArgumentException("Encoding não suportado: {$encoding}");
        }
    }

    /**
     * Comparação segura resistente a timing attacks
     */
    private function secureCompare(string $signature1, string $signature2, bool $caseSensitive = true): bool
    {
        if (!$caseSensitive) {
            $signature1 = strtolower($signature1);
            $signature2 = strtolower($signature2);
        }

        return hash_equals($signature1, $signature2);
    }

    /**
     * Obtém casos de uso do algoritmo
     */
    private function getAlgorithmUseCases(string $algorithm): array
    {
        $useCases = [
            'sha1' => ['Legacy systems', 'Git commits'],
            'sha256' => ['Webhooks', 'API authentication', 'JWT signing', 'General purpose'],
            'sha384' => ['High security applications', 'Certificate signing'],
            'sha512' => ['Maximum security', 'Cryptographic protocols', 'Password hashing'],
            'md5' => ['Legacy compatibility only (not recommended)'],
        ];

        return $useCases[$algorithm] ?? ['Unknown'];
    }

    /**
     * Calcula força da chave
     */
    public function calculateKeyStrength(string $key): array
    {
        $binaryKey = $this->prepareKey($key, []);
        $length = strlen($binaryKey);
        $entropy = $this->calculateEntropy($binaryKey);

        $strength = 'weak';
        if ($length >= 32 && $entropy > 7.5) {
            $strength = 'strong';
        } elseif ($length >= 24 && $entropy > 6.5) {
            $strength = 'medium';
        } elseif ($length >= 16 && $entropy > 5.5) {
            $strength = 'fair';
        }

        return [
            'length_bytes' => $length,
            'length_bits' => $length * 8,
            'entropy' => round($entropy, 2),
            'strength' => $strength,
            'is_secure' => $strength !== 'weak',
            'recommendations' => $this->getKeyRecommendations($length, $entropy),
        ];
    }

    /**
     * Calcula entropia de uma chave
     */
    private function calculateEntropy(string $key): float
    {
        $frequencies = array_count_values(str_split($key));
        $length = strlen($key);
        $entropy = 0;

        foreach ($frequencies as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    /**
     * Obtém recomendações para chave
     */
    private function getKeyRecommendations(int $length, float $entropy): array
    {
        $recommendations = [];

        if ($length < 32) {
            $recommendations[] = 'Usar chave de pelo menos 32 bytes para máxima segurança';
        }

        if ($entropy < 7.0) {
            $recommendations[] = 'Usar gerador de números aleatórios criptograficamente seguro';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Chave atende aos padrões de segurança recomendados';
        }

        return $recommendations;
    }
}