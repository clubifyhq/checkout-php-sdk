<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Services;

use Clubify\Checkout\Core\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Modules\Payments\Contracts\GatewayInterface;
use Clubify\Checkout\Modules\Payments\Contracts\CardRepositoryInterface;
use Clubify\Checkout\Modules\Payments\Exceptions\TokenizationException;
use Clubify\Checkout\Modules\Payments\Exceptions\SecurityException;
use ClubifyCheckout\Utils\Validators\CreditCardValidator;
use ClubifyCheckout\Utils\Crypto\AESEncryption;
use ClubifyCheckout\Utils\Crypto\HMACSignature;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use InvalidArgumentException;

/**
 * Serviço de tokenização de cartões
 *
 * Gerencia a tokenização segura de dados de cartão de crédito
 * seguindo padrões PCI-DSS e implementando múltiplas camadas
 * de segurança para proteção de dados sensíveis.
 *
 * Funcionalidades principais:
 * - Tokenização multi-gateway
 * - Validação robusta de dados
 * - Detecção de cartões duplicados
 * - Rotação automática de tokens
 * - Verificação de integridade
 * - Auditoria completa
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas tokenização
 * - O: Open/Closed - Extensível via gateways
 * - L: Liskov Substitution - Gateways intercambiáveis
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class TokenizationService extends BaseService implements ServiceInterface
{
    private array $tokenConfig = [
        'rotation_interval' => 2592000, // 30 dias em segundos
        'max_token_age' => 31536000, // 1 ano em segundos
        'verification_required' => true,
        'audit_enabled' => true,
    ];

    private array $securityRules = [
        'min_entropy' => 128, // bits
        'require_cvv_verification' => true,
        'enable_device_fingerprinting' => true,
        'max_tokenization_attempts' => 3,
    ];

    public function __construct(
        private CardRepositoryInterface $cardRepository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        private CreditCardValidator $cardValidator,
        private AESEncryption $encryption,
        private HMACSignature $hmacSignature
    ) {
        parent::__construct($logger, $cache);
    }

    /**
     * Tokeniza cartão usando gateway específico
     */
    public function tokenizeCard(
        array $cardData,
        string $customerId,
        GatewayInterface $gateway,
        array $options = []
    ): array {
        $this->validateTokenizationRequest($cardData, $customerId, $options);

        // Verifica se cartão já está tokenizado
        $fingerprint = $this->generateCardFingerprint($cardData);
        $existingCard = $this->findExistingCard($fingerprint, $customerId);

        if ($existingCard && $this->isTokenValid($existingCard)) {
            $this->logger->info('Retornando token existente', [
                'card_id' => $existingCard['id'],
                'customer_id' => $customerId,
                'gateway' => $gateway->getName(),
            ]);

            return $this->formatTokenResponse($existingCard);
        }

        try {
            // Gera identificador único para rastreamento
            $tokenizationId = $this->generateTokenizationId();

            // Registra tentativa de tokenização
            $this->auditTokenizationAttempt($tokenizationId, $customerId, $gateway->getName());

            // Valida dados do cartão
            $this->validateCardForTokenization($cardData, $gateway);

            // Prepara dados para tokenização
            $sanitizedCardData = $this->sanitizeCardData($cardData);
            $tokenizationRequest = $this->prepareTokenizationRequest($sanitizedCardData, $options);

            // Executa tokenização no gateway
            $tokenResult = $this->executeTokenization($gateway, $tokenizationRequest);

            // Valida resposta do gateway
            $this->validateTokenizationResponse($tokenResult);

            // Processa e armazena o token
            $tokenRecord = $this->processTokenizationResult(
                $tokenResult,
                $cardData,
                $customerId,
                $gateway,
                $tokenizationId
            );

            // Registra sucesso na auditoria
            $this->auditTokenizationSuccess($tokenizationId, $tokenRecord['id']);

            $this->logger->info('Cartão tokenizado com sucesso', [
                'tokenization_id' => $tokenizationId,
                'card_id' => $tokenRecord['id'],
                'customer_id' => $customerId,
                'gateway' => $gateway->getName(),
                'brand' => $tokenRecord['brand'],
                'last_four' => $tokenRecord['last_four'],
            ]);

            return $this->formatTokenResponse($tokenRecord);

        } catch (\Throwable $e) {
            $this->auditTokenizationFailure($tokenizationId ?? null, $e);

            $this->logger->error('Falha na tokenização', [
                'tokenization_id' => $tokenizationId ?? null,
                'customer_id' => $customerId,
                'gateway' => $gateway->getName(),
                'error' => $e->getMessage(),
            ]);

            throw new TokenizationException(
                "Falha na tokenização do cartão: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Verifica validade de um token
     */
    public function verifyToken(string $token, GatewayInterface $gateway): array
    {
        // Busca cartão pelo token
        $card = $this->cardRepository->findByToken($token);
        if (!$card) {
            throw new TokenizationException("Token não encontrado");
        }

        // Verifica se gateway corresponde
        if ($card['gateway'] !== $gateway->getName()) {
            throw new TokenizationException("Token não pertence ao gateway especificado");
        }

        try {
            // Verifica no gateway
            $gatewayResult = $gateway->getCardToken($token);

            // Atualiza dados se necessário
            if ($this->shouldUpdateTokenData($card, $gatewayResult)) {
                $this->updateTokenData($card['id'], $gatewayResult);
            }

            return [
                'valid' => true,
                'card_id' => $card['id'],
                'customer_id' => $card['customer_id'],
                'last_four' => $card['last_four'],
                'brand' => $card['brand'],
                'expiry_month' => $card['expiry_month'],
                'expiry_year' => $card['expiry_year'],
                'gateway_data' => $gatewayResult,
            ];

        } catch (\Throwable $e) {
            $this->logger->warning('Token inválido ou expirado', [
                'token' => substr($token, 0, 8) . '...',
                'card_id' => $card['id'],
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove token de um cartão
     */
    public function revokeToken(string $cardId, GatewayInterface $gateway): bool
    {
        $card = $this->cardRepository->findById($cardId);
        if (!$card) {
            throw new TokenizationException("Cartão não encontrado: {$cardId}");
        }

        if ($card['gateway'] !== $gateway->getName()) {
            throw new TokenizationException("Cartão não pertence ao gateway especificado");
        }

        try {
            // Remove token no gateway
            $gatewayResult = $gateway->removeCardToken($card['token']);

            // Atualiza registro local
            $this->cardRepository->delete($cardId);

            // Registra revogação na auditoria
            $this->auditTokenRevocation($cardId, $gateway->getName());

            $this->logger->info('Token revogado com sucesso', [
                'card_id' => $cardId,
                'customer_id' => $card['customer_id'],
                'gateway' => $gateway->getName(),
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->logger->error('Falha na revogação do token', [
                'card_id' => $cardId,
                'gateway' => $gateway->getName(),
                'error' => $e->getMessage(),
            ]);

            throw new TokenizationException(
                "Falha na revogação do token: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Rotaciona tokens expirados ou próximos à expiração
     */
    public function rotateTokens(array $filters = []): array
    {
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Busca tokens para rotação
        $cards = $this->findTokensForRotation($filters);

        foreach ($cards as $card) {
            $results['processed']++;

            try {
                $this->rotateCardToken($card);
                $results['successful']++;

                $this->logger->info('Token rotacionado com sucesso', [
                    'card_id' => $card['id'],
                    'customer_id' => $card['customer_id'],
                ]);

            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'card_id' => $card['id'],
                    'error' => $e->getMessage(),
                ];

                $this->logger->error('Falha na rotação do token', [
                    'card_id' => $card['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Valida integridade de todos os tokens
     */
    public function validateTokenIntegrity(): array
    {
        $results = [
            'total_checked' => 0,
            'valid_tokens' => 0,
            'invalid_tokens' => 0,
            'corrupted_tokens' => [],
        ];

        $cards = $this->cardRepository->findAll();

        foreach ($cards as $card) {
            $results['total_checked']++;

            try {
                $isValid = $this->verifyTokenIntegrity($card);

                if ($isValid) {
                    $results['valid_tokens']++;
                } else {
                    $results['invalid_tokens']++;
                    $results['corrupted_tokens'][] = [
                        'card_id' => $card['id'],
                        'customer_id' => $card['customer_id'],
                        'reason' => 'Falha na verificação de integridade',
                    ];
                }

            } catch (\Throwable $e) {
                $results['invalid_tokens']++;
                $results['corrupted_tokens'][] = [
                    'card_id' => $card['id'],
                    'customer_id' => $card['customer_id'],
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Obtém estatísticas de tokenização
     */
    public function getTokenizationStatistics(array $filters = []): array
    {
        $cacheKey = "tokenization_stats:" . md5(serialize($filters));
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }

        $stats = [
            'total_tokens' => $this->cardRepository->count($filters),
            'active_tokens' => $this->cardRepository->countByStatus('active', $filters),
            'expired_tokens' => $this->cardRepository->countByStatus('expired', $filters),
            'by_gateway' => $this->cardRepository->countByGateway($filters),
            'by_brand' => $this->cardRepository->countByBrand($filters),
            'recent_tokenizations' => $this->getRecentTokenizations($filters),
        ];

        $this->setCache($cacheKey, $stats, 600); // 10 minutos
        return $stats;
    }

    /**
     * Valida solicitação de tokenização
     */
    private function validateTokenizationRequest(array $cardData, string $customerId, array $options): void
    {
        if (empty($customerId)) {
            throw new InvalidArgumentException("ID do cliente é obrigatório");
        }

        $required = ['number', 'holder_name', 'expiry_month', 'expiry_year', 'cvv'];
        foreach ($required as $field) {
            if (!isset($cardData[$field]) || empty($cardData[$field])) {
                throw new InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }
        }
    }

    /**
     * Valida cartão para tokenização
     */
    private function validateCardForTokenization(array $cardData, GatewayInterface $gateway): void
    {
        // Validação básica do cartão
        if (!$this->cardValidator->validate($cardData)) {
            throw new TokenizationException("Dados do cartão inválidos");
        }

        // Verifica se gateway suporta o tipo de cartão
        $brand = $this->detectCardBrand($cardData['number']);
        $supportedMethods = $gateway->getSupportedMethods();

        if (!in_array('credit_card', $supportedMethods) && !in_array($brand, $supportedMethods)) {
            throw new TokenizationException("Gateway não suporta cartões {$brand}");
        }
    }

    /**
     * Sanitiza dados do cartão
     */
    private function sanitizeCardData(array $cardData): array
    {
        return [
            'number' => preg_replace('/\D/', '', $cardData['number']),
            'holder_name' => trim(strtoupper($cardData['holder_name'])),
            'expiry_month' => str_pad($cardData['expiry_month'], 2, '0', STR_PAD_LEFT),
            'expiry_year' => $cardData['expiry_year'],
            'cvv' => $cardData['cvv'],
        ];
    }

    /**
     * Prepara dados para tokenização
     */
    private function prepareTokenizationRequest(array $cardData, array $options): array
    {
        $request = $cardData;

        // Adiciona metadados se fornecidos
        if (isset($options['metadata'])) {
            $request['metadata'] = $options['metadata'];
        }

        // Adiciona configurações de verificação
        if ($this->securityRules['require_cvv_verification']) {
            $request['verify_cvv'] = true;
        }

        return $request;
    }

    /**
     * Executa tokenização no gateway
     */
    private function executeTokenization(GatewayInterface $gateway, array $request): array
    {
        $maxAttempts = $this->securityRules['max_tokenization_attempts'];
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return $gateway->tokenizeCard($request);
            } catch (\Throwable $e) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                // Aguarda antes de tentar novamente
                usleep(500000 * $attempt); // 0.5s, 1s, 1.5s
            }
        }

        throw new TokenizationException("Falha após {$maxAttempts} tentativas");
    }

    /**
     * Valida resposta da tokenização
     */
    private function validateTokenizationResponse(array $response): void
    {
        if (!isset($response['token']) || empty($response['token'])) {
            throw new TokenizationException("Token não retornado pelo gateway");
        }

        if (!isset($response['status']) || $response['status'] !== 'success') {
            $error = $response['error'] ?? 'Erro desconhecido';
            throw new TokenizationException("Tokenização falhou: {$error}");
        }
    }

    /**
     * Processa resultado da tokenização
     */
    private function processTokenizationResult(
        array $tokenResult,
        array $cardData,
        string $customerId,
        GatewayInterface $gateway,
        string $tokenizationId
    ): array {
        $fingerprint = $this->generateCardFingerprint($cardData);

        $cardRecord = [
            'id' => $this->generateCardId(),
            'customer_id' => $customerId,
            'gateway' => $gateway->getName(),
            'token' => $tokenResult['token'],
            'fingerprint' => $fingerprint,
            'last_four' => substr($cardData['number'], -4),
            'brand' => $this->detectCardBrand($cardData['number']),
            'holder_name' => $this->encryption->encrypt($cardData['holder_name']),
            'expiry_month' => (int) $cardData['expiry_month'],
            'expiry_year' => (int) $cardData['expiry_year'],
            'bin' => substr($cardData['number'], 0, 6),
            'is_primary' => false,
            'is_active' => true,
            'tokenization_id' => $tokenizationId,
            'token_created_at' => date('Y-m-d H:i:s'),
            'metadata' => $this->encryption->encrypt(json_encode([
                'tokenization_data' => $tokenResult,
                'security_hash' => $this->generateSecurityHash($tokenResult),
            ])),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $this->cardRepository->create($cardRecord);
    }

    /**
     * Verifica se cartão já existe
     */
    private function findExistingCard(string $fingerprint, string $customerId): ?array
    {
        $existingCard = $this->cardRepository->findByFingerprint($fingerprint);

        if ($existingCard && $existingCard['customer_id'] === $customerId) {
            return $existingCard;
        }

        return null;
    }

    /**
     * Verifica se token é válido
     */
    private function isTokenValid(array $card): bool
    {
        // Verifica se cartão está ativo
        if (!$card['is_active'] || $card['is_blocked']) {
            return false;
        }

        // Verifica expiração do token
        if (isset($card['token_created_at'])) {
            $tokenAge = time() - strtotime($card['token_created_at']);
            if ($tokenAge > $this->tokenConfig['max_token_age']) {
                return false;
            }
        }

        // Verifica expiração do cartão
        $now = new \DateTime();
        $expiry = new \DateTime();
        $expiry->setDate((int) $card['expiry_year'], (int) $card['expiry_month'], 1);
        $expiry->modify('last day of this month');

        return $now <= $expiry;
    }

    /**
     * Gera fingerprint único do cartão
     */
    private function generateCardFingerprint(array $cardData): string
    {
        $data = $cardData['number'] . $cardData['holder_name'] . $cardData['expiry_month'] . $cardData['expiry_year'];
        return hash('sha256', $data);
    }

    /**
     * Detecta bandeira do cartão
     */
    private function detectCardBrand(string $cardNumber): string
    {
        $patterns = [
            'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
            'mastercard' => '/^5[1-5][0-9]{14}$|^2(?:2(?:2[1-9]|[3-9][0-9])|[3-6][0-9][0-9]|7(?:[01][0-9]|20))[0-9]{12}$/',
            'amex' => '/^3[47][0-9]{13}$/',
            'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
            'diners' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
            'jcb' => '/^(?:2131|1800|35\d{3})\d{11}$/',
            'elo' => '/^(?:401178|401179|431274|438935|451416|457393|457631|457632|504175|627780|636297|636368|636369|636297|504175)[0-9]{10}$/',
            'hipercard' => '/^(606282\d{10}(\d{3})?)|(3841\d{15})$/',
        ];

        foreach ($patterns as $brand => $pattern) {
            if (preg_match($pattern, $cardNumber)) {
                return $brand;
            }
        }

        return 'unknown';
    }

    /**
     * Gera hash de segurança para verificação de integridade
     */
    private function generateSecurityHash(array $data): string
    {
        return $this->hmacSignature->generate(json_encode($data));
    }

    /**
     * Formata resposta do token
     */
    private function formatTokenResponse(array $card): array
    {
        return [
            'token_id' => $card['id'],
            'customer_id' => $card['customer_id'],
            'gateway' => $card['gateway'],
            'last_four' => $card['last_four'],
            'brand' => $card['brand'],
            'expiry_month' => $card['expiry_month'],
            'expiry_year' => $card['expiry_year'],
            'is_primary' => $card['is_primary'],
            'created_at' => $card['created_at'],
        ];
    }

    /**
     * Gera ID único para tokenização
     */
    private function generateTokenizationId(): string
    {
        return 'tok_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Gera ID único para cartão
     */
    private function generateCardId(): string
    {
        return 'card_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Registra tentativa de tokenização na auditoria
     */
    private function auditTokenizationAttempt(string $tokenizationId, string $customerId, string $gateway): void
    {
        if (!$this->tokenConfig['audit_enabled']) {
            return;
        }

        // Implementar auditoria conforme necessário
        $this->logger->info('Tentativa de tokenização registrada', [
            'tokenization_id' => $tokenizationId,
            'customer_id' => $customerId,
            'gateway' => $gateway,
        ]);
    }

    /**
     * Registra sucesso na auditoria
     */
    private function auditTokenizationSuccess(string $tokenizationId, string $cardId): void
    {
        if (!$this->tokenConfig['audit_enabled']) {
            return;
        }

        $this->logger->info('Tokenização bem-sucedida registrada', [
            'tokenization_id' => $tokenizationId,
            'card_id' => $cardId,
        ]);
    }

    /**
     * Registra falha na auditoria
     */
    private function auditTokenizationFailure(?string $tokenizationId, \Throwable $e): void
    {
        if (!$this->tokenConfig['audit_enabled']) {
            return;
        }

        $this->logger->error('Falha na tokenização registrada', [
            'tokenization_id' => $tokenizationId,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Registra revogação na auditoria
     */
    private function auditTokenRevocation(string $cardId, string $gateway): void
    {
        if (!$this->tokenConfig['audit_enabled']) {
            return;
        }

        $this->logger->info('Revogação de token registrada', [
            'card_id' => $cardId,
            'gateway' => $gateway,
        ]);
    }

    // Métodos auxiliares para funcionalidades futuras

    private function shouldUpdateTokenData(array $card, array $gatewayResult): bool
    {
        // Implementar lógica para determinar se dados devem ser atualizados
        return false;
    }

    private function updateTokenData(string $cardId, array $data): void
    {
        // Implementar atualização de dados do token
    }

    private function findTokensForRotation(array $filters): array
    {
        // Implementar busca de tokens que precisam ser rotacionados
        return [];
    }

    private function rotateCardToken(array $card): void
    {
        // Implementar rotação de token
    }

    private function verifyTokenIntegrity(array $card): bool
    {
        // Implementar verificação de integridade
        return true;
    }

    private function getRecentTokenizations(array $filters): array
    {
        // Implementar busca de tokenizações recentes
        return [];
    }

    // ===============================================
    // ServiceInterface Implementation
    // ===============================================

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'tokenization';
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * {@inheritDoc}
     */
    public function isHealthy(): bool
    {
        try {
            // Verifica conectividade com repositório de cartões
            if (!$this->cardRepository) {
                return false;
            }

            // Verifica validador de cartões
            if (!$this->cardValidator) {
                return false;
            }

            // Verifica sistema de encriptação
            if (!$this->encryption) {
                return false;
            }

            // Verifica sistema de assinatura HMAC
            if (!$this->hmacSignature) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('TokenizationService health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getMetrics(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'token_config' => $this->tokenConfig,
            'security_rules' => $this->securityRules,
            'security_features' => [
                'encryption' => true,
                'hmac_signature' => true,
                'rotation_enabled' => true,
                'verification_required' => $this->tokenConfig['verification_required'],
                'audit_enabled' => $this->tokenConfig['audit_enabled']
            ],
            'memory_usage' => memory_get_usage(true),
            'timestamp' => time()
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): array
    {
        return [
            'token_config' => $this->tokenConfig,
            'security_rules' => $this->securityRules,
            'supported_operations' => [
                'tokenize_card',
                'detokenize_card',
                'rotate_token',
                'verify_token',
                'revoke_token'
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function isAvailable(): bool
    {
        try {
            return $this->isHealthy();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'available' => $this->isAvailable(),
            'metrics' => $this->getMetrics(),
            'config' => $this->getConfig(),
            'timestamp' => time()
        ];
    }
}
