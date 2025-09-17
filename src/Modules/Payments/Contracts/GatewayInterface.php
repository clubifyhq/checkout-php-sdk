<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Payments\Contracts;

/**
 * Interface para Gateway de Pagamento
 *
 * Define o contrato que todos os gateways de pagamento
 * devem implementar usando o Strategy Pattern.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de gateway
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Todas implementações são intercambiáveis
 * - I: Interface Segregation - Interface específica para gateways
 * - D: Dependency Inversion - Abstração para implementações
 */
interface GatewayInterface
{
    /**
     * Obtém nome do gateway
     */
    public function getName(): string;

    /**
     * Obtém versão da API do gateway
     */
    public function getApiVersion(): string;

    /**
     * Verifica se gateway está ativo
     */
    public function isActive(): bool;

    /**
     * Obtém métodos de pagamento suportados
     */
    public function getSupportedMethods(): array;

    /**
     * Obtém moedas suportadas
     */
    public function getSupportedCurrencies(): array;

    /**
     * Processa um pagamento
     */
    public function processPayment(array $paymentData): array;

    /**
     * Autoriza um pagamento (sem captura)
     */
    public function authorizePayment(array $paymentData): array;

    /**
     * Captura um pagamento autorizado
     */
    public function capturePayment(string $authorizationId, ?float $amount = null): array;

    /**
     * Estorna um pagamento
     */
    public function refundPayment(string $paymentId, ?float $amount = null, string $reason = ''): array;

    /**
     * Cancela um pagamento autorizado
     */
    public function cancelPayment(string $authorizationId, string $reason = ''): array;

    /**
     * Busca dados de um pagamento
     */
    public function getPayment(string $paymentId): array;

    /**
     * Lista pagamentos por filtros
     */
    public function listPayments(array $filters = []): array;

    /**
     * Tokeniza cartão de crédito
     */
    public function tokenizeCard(array $cardData): array;

    /**
     * Remove token de cartão
     */
    public function removeCardToken(string $token): bool;

    /**
     * Obtém dados do token de cartão
     */
    public function getCardToken(string $token): array;

    /**
     * Valida dados de cartão
     */
    public function validateCard(array $cardData): array;

    /**
     * Detecta bandeira do cartão
     */
    public function detectCardBrand(string $cardNumber): string;

    /**
     * Calcula taxas do gateway
     */
    public function calculateFees(float $amount, string $method, string $currency = 'BRL'): array;

    /**
     * Verifica status de uma transação
     */
    public function checkTransactionStatus(string $transactionId): array;

    /**
     * Processa webhook do gateway
     */
    public function processWebhook(array $payload, array $headers = []): array;

    /**
     * Valida assinatura do webhook
     */
    public function validateWebhookSignature(array $payload, array $headers): bool;

    /**
     * Testa conectividade com o gateway
     */
    public function testConnection(): array;

    /**
     * Obtém configurações do gateway
     */
    public function getConfig(): array;

    /**
     * Define configurações do gateway
     */
    public function setConfig(array $config): void;

    /**
     * Verifica se método de pagamento é suportado
     */
    public function supportsMethod(string $method): bool;

    /**
     * Verifica se moeda é suportada
     */
    public function supportsCurrency(string $currency): bool;

    /**
     * Obtém limites de transação
     */
    public function getTransactionLimits(): array;

    /**
     * Verifica se valor está dentro dos limites
     */
    public function isAmountValid(float $amount, string $currency = 'BRL'): bool;

    /**
     * Formata valor para o gateway
     */
    public function formatAmount(float $amount, string $currency = 'BRL'): mixed;

    /**
     * Converte resposta do gateway para formato padrão
     */
    public function normalizeResponse(array $response): array;

    /**
     * Obtém URL de redirecionamento (para métodos que requerem)
     */
    public function getRedirectUrl(array $paymentData): ?string;

    /**
     * Processa retorno de redirecionamento
     */
    public function processRedirectReturn(array $returnData): array;

    /**
     * Obtém dados de bin do cartão
     */
    public function getBinData(string $bin): array;

    /**
     * Verifica se cartão está bloqueado
     */
    public function isCardBlocked(string $cardNumber): bool;

    /**
     * Obtém taxas de parcelamento
     */
    public function getInstallmentFees(float $amount, int $installments): array;

    /**
     * Calcula valor da parcela
     */
    public function calculateInstallmentAmount(float $amount, int $installments): float;

    /**
     * Obtém máximo de parcelas permitidas
     */
    public function getMaxInstallments(float $amount): int;

    /**
     * Gera boleto bancário
     */
    public function generateBoleto(array $boletoData): array;

    /**
     * Consulta status do boleto
     */
    public function getBoletoStatus(string $boletoId): array;

    /**
     * Gera QR Code PIX
     */
    public function generatePixQRCode(array $pixData): array;

    /**
     * Consulta status do PIX
     */
    public function getPixStatus(string $pixId): array;

    /**
     * Obtém chave PIX do recebedor
     */
    public function getPixKey(): ?string;

    /**
     * Registra chave PIX
     */
    public function registerPixKey(array $keyData): array;

    /**
     * Processa pagamento recorrente
     */
    public function processRecurringPayment(array $subscriptionData): array;

    /**
     * Cancela pagamento recorrente
     */
    public function cancelRecurringPayment(string $subscriptionId): array;

    /**
     * Atualiza pagamento recorrente
     */
    public function updateRecurringPayment(string $subscriptionId, array $updateData): array;

    /**
     * Obtém histórico de cobrança recorrente
     */
    public function getRecurringPaymentHistory(string $subscriptionId): array;

    /**
     * Obtém métricas de performance do gateway
     */
    public function getPerformanceMetrics(): array;

    /**
     * Obtém status atual do gateway
     */
    public function getGatewayStatus(): array;

    /**
     * Verifica se gateway está em manutenção
     */
    public function isUnderMaintenance(): bool;

    /**
     * Obtém tempo de resposta médio
     */
    public function getAverageResponseTime(): float;

    /**
     * Obtém taxa de sucesso das transações
     */
    public function getSuccessRate(): float;
}