<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Payments\Services;

use ClubifyCheckout\Core\BaseService;
use ClubifyCheckout\Modules\Payments\Contracts\CardRepositoryInterface;
use ClubifyCheckout\Modules\Payments\Contracts\GatewayInterface;
use ClubifyCheckout\Modules\Payments\Exceptions\CardException;
use ClubifyCheckout\Modules\Payments\Exceptions\ValidationException;
use ClubifyCheckout\Utils\Validators\CreditCardValidator;
use ClubifyCheckout\Utils\Formatters\CurrencyFormatter;
use ClubifyCheckout\Utils\Crypto\AESEncryption;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use InvalidArgumentException;

/**
 * Serviço de gestão de cartões
 *
 * Gerencia operações CRUD de cartões tokenizados,
 * validação, verificação de segurança e gestão de estados.
 *
 * Implementa segurança PCI-DSS:
 * - Tokenização de dados sensíveis
 * - Criptografia de metadados
 * - Validação robusta de cartões
 * - Detecção de fraude básica
 * - Controle de uso e estado
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas gestão de cartões
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Substituível por outras implementações
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class CardService extends BaseService
{
    private array $fraudRules = [
        'max_attempts_per_hour' => 10,
        'max_failures_per_day' => 5,
        'suspicious_patterns' => [
            'rapid_sequential_attempts',
            'multiple_different_cards_same_customer',
            'expired_card_attempts',
        ],
    ];

    public function __construct(
        private CardRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        private CreditCardValidator $cardValidator,
        private AESEncryption $encryption
    ) {
        parent::__construct($logger, $cache);
    }

    /**
     * Tokeniza e salva cartão
     */
    public function tokenizeCard(array $cardData, string $customerId, GatewayInterface $gateway): array
    {
        $this->validateCardData($cardData);

        // Verifica se cartão já existe
        $fingerprint = $this->generateCardFingerprint($cardData);
        if ($this->repository->cardExists($fingerprint, $customerId)) {
            $existingCard = $this->repository->findByFingerprint($fingerprint);
            if ($existingCard && $existingCard['customer_id'] === $customerId) {
                return $existingCard;
            }
        }

        try {
            // Tokeniza no gateway
            $tokenResult = $gateway->tokenizeCard($cardData);

            // Prepara dados para salvar
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
                'is_blocked' => false,
                'is_suspicious' => false,
                'usage_count' => 0,
                'failure_count' => 0,
                'metadata' => $this->encryption->encrypt(json_encode([
                    'tokenized_at' => date('Y-m-d H:i:s'),
                    'gateway_data' => $tokenResult,
                ])),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // Obtém dados BIN se disponível
            try {
                $binData = $gateway->getBinData($cardRecord['bin']);
                $cardRecord['bin_data'] = json_encode($binData);
            } catch (\Throwable $e) {
                $this->logger->warning('Falha ao obter dados BIN', [
                    'bin' => $cardRecord['bin'],
                    'error' => $e->getMessage(),
                ]);
            }

            // Salva cartão
            $savedCard = $this->repository->create($cardRecord);

            // Define como principal se for o primeiro cartão
            $customerCards = $this->repository->findByCustomer($customerId, ['active' => true]);
            if (count($customerCards) === 1) {
                $this->markAsPrimary($savedCard['id'], $customerId);
            }

            $this->logger->info('Cartão tokenizado com sucesso', [
                'card_id' => $savedCard['id'],
                'customer_id' => $customerId,
                'gateway' => $gateway->getName(),
                'brand' => $cardRecord['brand'],
                'last_four' => $cardRecord['last_four'],
            ]);

            return $this->sanitizeCardData($savedCard);

        } catch (\Throwable $e) {
            $this->logger->error('Falha na tokenização do cartão', [
                'customer_id' => $customerId,
                'gateway' => $gateway->getName(),
                'error' => $e->getMessage(),
            ]);

            throw new CardException(
                "Falha na tokenização do cartão: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Obtém cartão por ID
     */
    public function getCard(string $cardId): ?array
    {
        $cacheKey = "card:{$cardId}";
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }

        $card = $this->repository->findById($cardId);
        if ($card) {
            $sanitized = $this->sanitizeCardData($card);
            $this->setCache($cacheKey, $sanitized, 300); // 5 minutos
            return $sanitized;
        }

        return null;
    }

    /**
     * Lista cartões do cliente
     */
    public function getCustomerCards(string $customerId, array $filters = []): array
    {
        $cacheKey = "customer_cards:{$customerId}:" . md5(serialize($filters));
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }

        $cards = $this->repository->findByCustomer($customerId, $filters);
        $sanitized = array_map([$this, 'sanitizeCardData'], $cards);

        $this->setCache($cacheKey, $sanitized, 180); // 3 minutos
        return $sanitized;
    }

    /**
     * Obtém cartão principal do cliente
     */
    public function getPrimaryCard(string $customerId): ?array
    {
        $cacheKey = "primary_card:{$customerId}";
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }

        $card = $this->repository->getPrimaryCard($customerId);
        if ($card) {
            $sanitized = $this->sanitizeCardData($card);
            $this->setCache($cacheKey, $sanitized, 300);
            return $sanitized;
        }

        return null;
    }

    /**
     * Define cartão como principal
     */
    public function markAsPrimary(string $cardId, string $customerId): array
    {
        $card = $this->repository->findById($cardId);
        if (!$card) {
            throw new CardException("Cartão não encontrado: {$cardId}");
        }

        if ($card['customer_id'] !== $customerId) {
            throw new CardException("Cartão não pertence ao cliente");
        }

        if (!$card['is_active'] || $card['is_blocked']) {
            throw new CardException("Cartão deve estar ativo e não bloqueado");
        }

        // Remove marcação de outros cartões
        $this->repository->unmarkPrimary($customerId);

        // Marca como principal
        $updatedCard = $this->repository->markAsPrimary($cardId, $customerId);

        // Limpa cache relacionado
        $this->clearCachePattern("card:{$cardId}");
        $this->clearCachePattern("primary_card:{$customerId}");
        $this->clearCachePattern("customer_cards:{$customerId}:*");

        $this->logger->info('Cartão marcado como principal', [
            'card_id' => $cardId,
            'customer_id' => $customerId,
        ]);

        return $this->sanitizeCardData($updatedCard);
    }

    /**
     * Atualiza dados do cartão
     */
    public function updateCard(string $cardId, array $updateData): array
    {
        $card = $this->repository->findById($cardId);
        if (!$card) {
            throw new CardException("Cartão não encontrado: {$cardId}");
        }

        $allowedFields = ['holder_name', 'expiry_month', 'expiry_year'];
        $updateFields = [];

        foreach ($allowedFields as $field) {
            if (isset($updateData[$field])) {
                if ($field === 'holder_name') {
                    $updateFields[$field] = $this->encryption->encrypt($updateData[$field]);
                } else {
                    $updateFields[$field] = $updateData[$field];
                }
            }
        }

        if (empty($updateFields)) {
            throw new InvalidArgumentException("Nenhum campo válido para atualização");
        }

        $updateFields['updated_at'] = date('Y-m-d H:i:s');
        $updatedCard = $this->repository->update($cardId, $updateFields);

        // Limpa cache
        $this->clearCardCache($cardId, $card['customer_id']);

        $this->logger->info('Cartão atualizado', [
            'card_id' => $cardId,
            'updated_fields' => array_keys($updateFields),
        ]);

        return $this->sanitizeCardData($updatedCard);
    }

    /**
     * Ativa cartão
     */
    public function activateCard(string $cardId): array
    {
        $card = $this->repository->findById($cardId);
        if (!$card) {
            throw new CardException("Cartão não encontrado: {$cardId}");
        }

        if ($card['is_active']) {
            throw new CardException("Cartão já está ativo");
        }

        $updatedCard = $this->repository->activate($cardId);
        $this->clearCardCache($cardId, $card['customer_id']);

        $this->logger->info('Cartão ativado', ['card_id' => $cardId]);

        return $this->sanitizeCardData($updatedCard);
    }

    /**
     * Desativa cartão
     */
    public function deactivateCard(string $cardId, string $reason = ''): array
    {
        $card = $this->repository->findById($cardId);
        if (!$card) {
            throw new CardException("Cartão não encontrado: {$cardId}");
        }

        // Se for cartão principal, marca outro como principal
        if ($card['is_primary']) {
            $otherCards = $this->repository->findByCustomer(
                $card['customer_id'],
                ['active' => true, 'exclude_id' => $cardId]
            );

            if (!empty($otherCards)) {
                $this->repository->markAsPrimary($otherCards[0]['id'], $card['customer_id']);
            }
        }

        $updatedCard = $this->repository->deactivate($cardId, $reason);
        $this->clearCardCache($cardId, $card['customer_id']);

        $this->logger->info('Cartão desativado', [
            'card_id' => $cardId,
            'reason' => $reason,
        ]);

        return $this->sanitizeCardData($updatedCard);
    }

    /**
     * Bloqueia cartão
     */
    public function blockCard(string $cardId, string $reason): array
    {
        $card = $this->repository->findById($cardId);
        if (!$card) {
            throw new CardException("Cartão não encontrado: {$cardId}");
        }

        $updatedCard = $this->repository->block($cardId, $reason);
        $this->clearCardCache($cardId, $card['customer_id']);

        $this->logger->warning('Cartão bloqueado', [
            'card_id' => $cardId,
            'reason' => $reason,
        ]);

        return $this->sanitizeCardData($updatedCard);
    }

    /**
     * Desbloqueia cartão
     */
    public function unblockCard(string $cardId): array
    {
        $card = $this->repository->findById($cardId);
        if (!$card) {
            throw new CardException("Cartão não encontrado: {$cardId}");
        }

        $updatedCard = $this->repository->unblock($cardId);
        $this->clearCardCache($cardId, $card['customer_id']);

        $this->logger->info('Cartão desbloqueado', ['card_id' => $cardId]);

        return $this->sanitizeCardData($updatedCard);
    }

    /**
     * Registra uso do cartão
     */
    public function registerCardUsage(string $cardId, array $usageData): array
    {
        $card = $this->repository->findById($cardId);
        if (!$card) {
            throw new CardException("Cartão não encontrado: {$cardId}");
        }

        // Verifica se cartão pode ser usado
        if (!$this->canUseCard($card)) {
            throw new CardException("Cartão não pode ser usado");
        }

        // Verifica regras de fraude
        $this->checkFraudRules($cardId, $usageData);

        // Registra uso
        $this->repository->addUsage($cardId, [
            'amount' => $usageData['amount'] ?? null,
            'currency' => $usageData['currency'] ?? 'BRL',
            'merchant' => $usageData['merchant'] ?? null,
            'ip_address' => $usageData['ip_address'] ?? null,
            'user_agent' => $usageData['user_agent'] ?? null,
            'success' => $usageData['success'] ?? false,
            'error_code' => $usageData['error_code'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Atualiza contadores
        if ($usageData['success'] ?? false) {
            $this->repository->updateLastUsed($cardId);
            $this->repository->resetFailureCount($cardId);
        } else {
            $this->repository->incrementFailureCount($cardId);
        }

        $this->clearCardCache($cardId, $card['customer_id']);

        return $this->sanitizeCardData($this->repository->findById($cardId));
    }

    /**
     * Verifica se cartão expirou
     */
    public function isExpired(array $card): bool
    {
        $now = new \DateTime();
        $expiry = new \DateTime();
        $expiry->setDate((int) $card['expiry_year'], (int) $card['expiry_month'], 1);
        $expiry->modify('last day of this month');

        return $now > $expiry;
    }

    /**
     * Verifica se cartão expira em N meses
     */
    public function isExpiringInMonths(array $card, int $months = 2): bool
    {
        $now = new \DateTime();
        $checkDate = clone $now;
        $checkDate->modify("+{$months} months");

        $expiry = new \DateTime();
        $expiry->setDate((int) $card['expiry_year'], (int) $card['expiry_month'], 1);
        $expiry->modify('last day of this month');

        return $expiry <= $checkDate && $expiry > $now;
    }

    /**
     * Obtém cartões próximos ao vencimento
     */
    public function getExpiringCards(int $months = 2): array
    {
        $cards = $this->repository->findExpiringNext($months);
        return array_map([$this, 'sanitizeCardData'], $cards);
    }

    /**
     * Obtém estatísticas de cartões
     */
    public function getCardStatistics(array $filters = []): array
    {
        $cacheKey = "card_stats:" . md5(serialize($filters));
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }

        $stats = $this->repository->getStatistics($filters);
        $this->setCache($cacheKey, $stats, 600); // 10 minutos

        return $stats;
    }

    /**
     * Remove cartão (soft delete)
     */
    public function deleteCard(string $cardId): bool
    {
        $card = $this->repository->findById($cardId);
        if (!$card) {
            throw new CardException("Cartão não encontrado: {$cardId}");
        }

        // Se for cartão principal, marca outro como principal
        if ($card['is_primary']) {
            $otherCards = $this->repository->findByCustomer(
                $card['customer_id'],
                ['active' => true, 'exclude_id' => $cardId]
            );

            if (!empty($otherCards)) {
                $this->repository->markAsPrimary($otherCards[0]['id'], $card['customer_id']);
            }
        }

        $result = $this->repository->delete($cardId);
        $this->clearCardCache($cardId, $card['customer_id']);

        $this->logger->info('Cartão removido', ['card_id' => $cardId]);

        return $result;
    }

    /**
     * Valida dados do cartão
     */
    private function validateCardData(array $cardData): void
    {
        $required = ['number', 'holder_name', 'expiry_month', 'expiry_year', 'cvv'];
        foreach ($required as $field) {
            if (!isset($cardData[$field]) || empty($cardData[$field])) {
                throw new ValidationException("Campo obrigatório ausente: {$field}");
            }
        }

        // Valida número do cartão
        if (!$this->cardValidator->validateNumber($cardData['number'])) {
            throw new ValidationException("Número do cartão inválido");
        }

        // Valida CVV
        if (!$this->cardValidator->validateCvv($cardData['cvv'], $cardData['number'])) {
            throw new ValidationException("CVV inválido");
        }

        // Valida data de expiração
        if (!$this->cardValidator->validateExpiry($cardData['expiry_month'], $cardData['expiry_year'])) {
            throw new ValidationException("Data de expiração inválida");
        }
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
     * Verifica se cartão pode ser usado
     */
    private function canUseCard(array $card): bool
    {
        if (!$card['is_active'] || $card['is_blocked']) {
            return false;
        }

        if ($this->isExpired($card)) {
            return false;
        }

        if ($this->repository->isBlockedByFailures($card['id'])) {
            return false;
        }

        return true;
    }

    /**
     * Verifica regras de fraude
     */
    private function checkFraudRules(string $cardId, array $usageData): void
    {
        // Verifica tentativas por hora
        $attempts = $this->repository->getUsageAttempts($cardId);
        $recentAttempts = array_filter($attempts, function ($attempt) {
            return strtotime($attempt['created_at']) > (time() - 3600); // 1 hora
        });

        if (count($recentAttempts) >= $this->fraudRules['max_attempts_per_hour']) {
            $this->repository->markAsSuspicious($cardId, 'Muitas tentativas em uma hora');
            throw new CardException("Muitas tentativas de uso do cartão");
        }

        // Outras verificações de fraude podem ser adicionadas aqui
    }

    /**
     * Remove dados sensíveis do cartão
     */
    private function sanitizeCardData(array $card): array
    {
        // Remove dados sensíveis
        unset($card['token'], $card['fingerprint']);

        // Descriptografa dados necessários
        if (isset($card['holder_name'])) {
            try {
                $card['holder_name'] = $this->encryption->decrypt($card['holder_name']);
            } catch (\Throwable $e) {
                $card['holder_name'] = '***';
            }
        }

        if (isset($card['metadata'])) {
            try {
                $metadata = json_decode($this->encryption->decrypt($card['metadata']), true);
                $card['metadata'] = $metadata;
            } catch (\Throwable $e) {
                unset($card['metadata']);
            }
        }

        return $card;
    }

    /**
     * Limpa cache relacionado ao cartão
     */
    private function clearCardCache(string $cardId, string $customerId): void
    {
        $this->clearCachePattern("card:{$cardId}");
        $this->clearCachePattern("primary_card:{$customerId}");
        $this->clearCachePattern("customer_cards:{$customerId}:*");
    }

    /**
     * Gera ID único para cartão
     */
    private function generateCardId(): string
    {
        return 'card_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
}