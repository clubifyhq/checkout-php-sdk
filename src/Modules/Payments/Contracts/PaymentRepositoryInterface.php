<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Payments\Contracts;

use ClubifyCheckout\Contracts\RepositoryInterface;

/**
 * Interface para Repository de Pagamentos
 *
 * Especializa o RepositoryInterface para operações
 * específicas de pagamentos e transações.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de pagamento
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Pode substituir RepositoryInterface
 * - I: Interface Segregation - Interface específica para pagamentos
 * - D: Dependency Inversion - Abstração para implementações
 */
interface PaymentRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca pagamentos por organização
     */
    public function findByOrganization(string $organizationId, array $filters = []): array;

    /**
     * Busca pagamentos por cliente
     */
    public function findByCustomer(string $customerId, array $filters = []): array;

    /**
     * Busca pagamentos por sessão
     */
    public function findBySession(string $sessionId): array;

    /**
     * Busca pagamentos por pedido
     */
    public function findByOrder(string $orderId): array;

    /**
     * Busca pagamentos por gateway
     */
    public function findByGateway(string $gateway, array $filters = []): array;

    /**
     * Busca pagamentos por status
     */
    public function findByStatus(string $status, array $filters = []): array;

    /**
     * Busca pagamentos por método
     */
    public function findByMethod(string $method, array $filters = []): array;

    /**
     * Busca pagamentos por período
     */
    public function findByDateRange(string $startDate, string $endDate, array $filters = []): array;

    /**
     * Busca pagamentos pendentes
     */
    public function findPending(array $filters = []): array;

    /**
     * Busca pagamentos processando
     */
    public function findProcessing(array $filters = []): array;

    /**
     * Busca pagamentos autorizados
     */
    public function findAuthorized(array $filters = []): array;

    /**
     * Busca pagamentos capturados
     */
    public function findCaptured(array $filters = []): array;

    /**
     * Busca pagamentos falharam
     */
    public function findFailed(array $filters = []): array;

    /**
     * Busca pagamentos cancelados
     */
    public function findCancelled(array $filters = []): array;

    /**
     * Busca pagamentos estornados
     */
    public function findRefunded(array $filters = []): array;

    /**
     * Busca pagamentos disputados
     */
    public function findDisputed(array $filters = []): array;

    /**
     * Atualiza status do pagamento
     */
    public function updateStatus(string $id, string $status, array $data = []): array;

    /**
     * Atualiza dados do gateway
     */
    public function updateGatewayData(string $id, array $gatewayData): array;

    /**
     * Adiciona tentativa de pagamento
     */
    public function addAttempt(string $id, array $attemptData): array;

    /**
     * Obtém tentativas de pagamento
     */
    public function getAttempts(string $id): array;

    /**
     * Adiciona evento ao pagamento
     */
    public function addEvent(string $id, array $event): array;

    /**
     * Obtém eventos do pagamento
     */
    public function getEvents(string $id): array;

    /**
     * Marca pagamento como processando
     */
    public function markAsProcessing(string $id): array;

    /**
     * Marca pagamento como autorizado
     */
    public function markAsAuthorized(string $id, array $authData): array;

    /**
     * Marca pagamento como capturado
     */
    public function markAsCaptured(string $id, array $captureData): array;

    /**
     * Marca pagamento como falhou
     */
    public function markAsFailed(string $id, string $reason, array $errorData = []): array;

    /**
     * Marca pagamento como cancelado
     */
    public function markAsCancelled(string $id, string $reason): array;

    /**
     * Marca pagamento como estornado
     */
    public function markAsRefunded(string $id, float $amount, string $reason): array;

    /**
     * Marca pagamento como disputado
     */
    public function markAsDisputed(string $id, array $disputeData): array;

    /**
     * Adiciona refund ao pagamento
     */
    public function addRefund(string $id, array $refundData): array;

    /**
     * Obtém refunds do pagamento
     */
    public function getRefunds(string $id): array;

    /**
     * Calcula total de refunds
     */
    public function getTotalRefunded(string $id): float;

    /**
     * Verifica se pagamento pode ser estornado
     */
    public function canBeRefunded(string $id): bool;

    /**
     * Obtém valor disponível para estorno
     */
    public function getRefundableAmount(string $id): float;

    /**
     * Adiciona chargeback ao pagamento
     */
    public function addChargeback(string $id, array $chargebackData): array;

    /**
     * Obtém chargebacks do pagamento
     */
    public function getChargebacks(string $id): array;

    /**
     * Atualiza dados de risco
     */
    public function updateRiskData(string $id, array $riskData): array;

    /**
     * Obtém dados de risco
     */
    public function getRiskData(string $id): array;

    /**
     * Atualiza dados de antifraude
     */
    public function updateAntiFraudData(string $id, array $antiFraudData): array;

    /**
     * Obtém dados de antifraude
     */
    public function getAntiFraudData(string $id): array;

    /**
     * Busca pagamentos suspeitos
     */
    public function findSuspicious(array $filters = []): array;

    /**
     * Busca pagamentos com alto risco
     */
    public function findHighRisk(array $filters = []): array;

    /**
     * Atualiza dados de parcelamento
     */
    public function updateInstallmentData(string $id, array $installmentData): array;

    /**
     * Obtém dados de parcelamento
     */
    public function getInstallmentData(string $id): array;

    /**
     * Obtém estatísticas de pagamentos
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Obtém estatísticas por gateway
     */
    public function getGatewayStatistics(array $filters = []): array;

    /**
     * Obtém estatísticas por método
     */
    public function getMethodStatistics(array $filters = []): array;

    /**
     * Conta pagamentos por status
     */
    public function countByStatus(array $filters = []): array;

    /**
     * Conta pagamentos por período
     */
    public function countByPeriod(string $period = 'day', array $filters = []): array;

    /**
     * Obtém volume financeiro
     */
    public function getVolumeByPeriod(string $period = 'day', array $filters = []): array;

    /**
     * Obtém taxa de sucesso
     */
    public function getSuccessRate(array $filters = []): float;

    /**
     * Obtém taxa de falha
     */
    public function getFailureRate(array $filters = []): float;

    /**
     * Obtém valor médio de transação
     */
    public function getAverageTicket(array $filters = []): float;

    /**
     * Obtém relatório de transações
     */
    public function getTransactionReport(array $filters = []): array;

    /**
     * Obtém relatório de reconciliação
     */
    public function getReconciliationReport(string $date): array;

    /**
     * Busca transações para reconciliação
     */
    public function findForReconciliation(string $gateway, string $date): array;

    /**
     * Marca transação como reconciliada
     */
    public function markAsReconciled(string $id, array $reconciliationData): array;

    /**
     * Obtém pagamentos não reconciliados
     */
    public function findUnreconciled(string $gateway, int $daysAgo = 1): array;

    /**
     * Limpa dados antigos de pagamentos
     */
    public function cleanupOldData(int $daysAgo = 90): int;

    /**
     * Arquiva pagamentos antigos
     */
    public function archiveOldPayments(int $daysAgo = 365): int;

    /**
     * Obtém pagamentos para retry
     */
    public function findForRetry(array $filters = []): array;

    /**
     * Marca pagamento para retry
     */
    public function markForRetry(string $id, string $reason): array;

    /**
     * Obtém próximo pagamento para processamento
     */
    public function getNextForProcessing(): ?array;

    /**
     * Bloqueia pagamento para processamento
     */
    public function lockForProcessing(string $id): bool;

    /**
     * Libera lock de processamento
     */
    public function releaseLock(string $id): bool;
}