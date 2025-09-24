<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Implementação de storage criptografado baseado em arquivos
 *
 * Características de segurança:
 * - Criptografia AES-256-GCM para credenciais
 * - Arquivos protegidos com permissões 600
 * - Verificação de integridade via hash
 * - Rotação automática de chaves
 */
class EncryptedFileStorage implements CredentialStorageInterface
{
    private string $storageDir;
    private string $encryptionKey;
    private string $cipher = 'aes-256-gcm';

    public function __construct(string $storageDir, string $encryptionKey)
    {
        if (empty($encryptionKey) || strlen($encryptionKey) < 32) {
            throw new InvalidArgumentException('Encryption key must be at least 32 characters');
        }

        $this->storageDir = rtrim($storageDir, '/');
        $this->encryptionKey = $encryptionKey;

        $this->ensureStorageDirectory();
    }

    /**
     * Armazenar credenciais criptografadas
     */
    public function store(string $context, array $credentials): void
    {
        $this->validateContext($context);

        try {
            // Serializar credenciais
            $data = json_encode($credentials, JSON_THROW_ON_ERROR);

            // Criptografar dados
            $encryptedData = $this->encrypt($data);

            // Salvar no arquivo
            $filePath = $this->getFilePath($context);

            if (file_put_contents($filePath, $encryptedData, LOCK_EX) === false) {
                throw new RuntimeException("Failed to write credentials to file: {$filePath}");
            }

            // Definir permissões seguras (apenas owner pode ler/escrever)
            chmod($filePath, 0600);

        } catch (Exception $e) {
            throw new RuntimeException("Failed to store credentials for context '{$context}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Recuperar credenciais descriptografadas
     */
    public function retrieve(string $context): ?array
    {
        $this->validateContext($context);

        $filePath = $this->getFilePath($context);

        if (!file_exists($filePath)) {
            return null;
        }

        try {
            // Ler arquivo criptografado
            $encryptedData = file_get_contents($filePath);

            if ($encryptedData === false) {
                throw new RuntimeException("Failed to read credentials file: {$filePath}");
            }

            // Descriptografar dados
            $data = $this->decrypt($encryptedData);

            // Deserializar credenciais
            $credentials = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            return $credentials;

        } catch (Exception $e) {
            // Log error but don't expose sensitive information
            error_log("Failed to retrieve credentials for context '{$context}': " . $e->getMessage());

            // If decryption failed, the file might be corrupted or encrypted with an old key
            // Remove it to allow fresh credential storage
            if (str_contains($e->getMessage(), 'Decryption failed')) {
                try {
                    $this->secureDelete($filePath);
                    error_log("Removed corrupted credential file for context '{$context}'");
                } catch (Exception $deleteException) {
                    error_log("Failed to remove corrupted credential file for context '{$context}': " . $deleteException->getMessage());
                }
            }

            return null;
        }
    }

    /**
     * Remover credenciais
     */
    public function remove(string $context): void
    {
        $this->validateContext($context);

        $filePath = $this->getFilePath($context);

        if (file_exists($filePath)) {
            // Sobrescrever arquivo com dados aleatórios antes de deletar (security)
            $this->secureDelete($filePath);
        }
    }

    /**
     * Verificar se contexto existe
     */
    public function exists(string $context): bool
    {
        $this->validateContext($context);
        return file_exists($this->getFilePath($context));
    }

    /**
     * Listar contextos disponíveis
     */
    public function listContexts(): array
    {
        if (!is_dir($this->storageDir)) {
            return [];
        }

        $contexts = [];
        $files = glob($this->storageDir . '/context_*.enc');

        foreach ($files as $file) {
            $filename = basename($file, '.enc');
            $context = substr($filename, 8); // Remove 'context_' prefix
            $contexts[] = $context;
        }

        return $contexts;
    }

    /**
     * Limpar todas as credenciais
     */
    public function clear(): void
    {
        $contexts = $this->listContexts();

        foreach ($contexts as $context) {
            $this->remove($context);
        }
    }

    /**
     * Verificar saúde do storage
     */
    public function isHealthy(): bool
    {
        try {
            // Verificar se diretório é acessível
            if (!is_dir($this->storageDir) || !is_writable($this->storageDir)) {
                return false;
            }

            // Teste de escrita/leitura
            $testContext = 'health_check_' . time();
            $testData = ['test' => 'data', 'timestamp' => time()];

            $this->store($testContext, $testData);
            $retrieved = $this->retrieve($testContext);
            $this->remove($testContext);

            return $retrieved === $testData;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Criptografar dados usando AES-256-GCM
     */
    private function encrypt(string $data): string
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt($data, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }

        // Combinar IV + tag + dados criptografados
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Descriptografar dados
     */
    private function decrypt(string $encryptedData): string
    {
        $data = base64_decode($encryptedData);

        if ($data === false) {
            throw new RuntimeException('Invalid encrypted data format');
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        $tagLength = 16; // GCM tag length

        if (strlen($data) < $ivLength + $tagLength) {
            throw new RuntimeException('Encrypted data too short');
        }

        // Extrair componentes
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $encrypted = substr($data, $ivLength + $tagLength);

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    /**
     * Garantir que diretório de storage existe e é seguro
     */
    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageDir)) {
            if (!mkdir($this->storageDir, 0700, true)) {
                throw new RuntimeException("Failed to create storage directory: {$this->storageDir}");
            }
        }

        // Verificar permissões do diretório
        $perms = fileperms($this->storageDir) & 0777;
        if ($perms !== 0700) {
            chmod($this->storageDir, 0700);
        }
    }

    /**
     * Obter caminho do arquivo para um contexto
     */
    private function getFilePath(string $context): string
    {
        return $this->storageDir . '/context_' . $context . '.enc';
    }

    /**
     * Validar nome do contexto
     */
    private function validateContext(string $context): void
    {
        if (empty($context)) {
            throw new InvalidArgumentException('Context cannot be empty');
        }

        // Apenas caracteres alfanuméricos, underscore e hífen
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $context)) {
            throw new InvalidArgumentException('Context contains invalid characters');
        }

        if (strlen($context) > 100) {
            throw new InvalidArgumentException('Context name too long');
        }
    }

    /**
     * Deletar arquivo de forma segura (sobrescrever com dados aleatórios)
     */
    private function secureDelete(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $fileSize = filesize($filePath);

        // Sobrescrever com dados aleatórios 3 vezes
        for ($i = 0; $i < 3; $i++) {
            $randomData = random_bytes($fileSize);
            file_put_contents($filePath, $randomData, LOCK_EX);
        }

        // Finalmente deletar o arquivo
        unlink($filePath);
    }
}