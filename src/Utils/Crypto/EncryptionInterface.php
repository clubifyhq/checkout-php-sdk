<?php

declare(strict_types=1);

namespace ClubifyCheckout\Utils\Crypto;

/**
 * Interface para sistemas de criptografia
 *
 * Define o contrato que todos os sistemas de criptografia
 * devem seguir, garantindo consistência na API e permitindo
 * extensibilidade para diferentes algoritmos.
 *
 * Implementações devem:
 * - Fornecer criptografia segura
 * - Ser thread-safe e stateless
 * - Implementar validação de entrada
 * - Ter performance otimizada
 * - Seguir padrões de segurança
 *
 * Compliance de Segurança:
 * - Usar algoritmos aprovados (AES-256-GCM)
 * - Implementar IV/nonce únicos
 * - Validar integridade dos dados
 * - Proteger contra timing attacks
 * - Seguir OWASP guidelines
 */
interface EncryptionInterface
{
    /**
     * Criptografa dados
     *
     * @param string $data Dados a serem criptografados
     * @param string $key Chave de criptografia
     * @param array $options Opções adicionais
     * @return string Dados criptografados (base64 encoded)
     * @throws \InvalidArgumentException Para parâmetros inválidos
     * @throws \RuntimeException Para erros de criptografia
     */
    public function encrypt(string $data, string $key, array $options = []): string;

    /**
     * Descriptografa dados
     *
     * @param string $encryptedData Dados criptografados (base64 encoded)
     * @param string $key Chave de descriptografia
     * @param array $options Opções adicionais
     * @return string Dados descriptografados
     * @throws \InvalidArgumentException Para parâmetros inválidos
     * @throws \RuntimeException Para erros de descriptografia
     */
    public function decrypt(string $encryptedData, string $key, array $options = []): string;

    /**
     * Gera chave de criptografia segura
     *
     * @param int $length Comprimento da chave em bytes
     * @return string Chave gerada (base64 encoded)
     */
    public function generateKey(int $length = 32): string;

    /**
     * Deriva chave a partir de senha
     *
     * @param string $password Senha base
     * @param string $salt Salt para derivação
     * @param int $iterations Número de iterações
     * @param int $length Comprimento da chave derivada
     * @return string Chave derivada (base64 encoded)
     */
    public function deriveKey(string $password, string $salt, int $iterations = 10000, int $length = 32): string;

    /**
     * Verifica se os dados podem ser descriptografados
     *
     * @param string $encryptedData Dados criptografados
     * @param string $key Chave de descriptografia
     * @return bool True se válido, false caso contrário
     */
    public function canDecrypt(string $encryptedData, string $key): bool;

    /**
     * Obtém informações sobre o algoritmo
     *
     * @return array Informações do algoritmo
     */
    public function getAlgorithmInfo(): array;
}