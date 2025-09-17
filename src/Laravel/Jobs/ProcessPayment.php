<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Jobs;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\PaymentException;
use Clubify\Checkout\Laravel\Facades\ClubifyCheckout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para processamento assíncrono de pagamentos
 */
final class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Dados do pagamento
     */
    private array $paymentData;

    /**
     * Opções de processamento
     */
    private array $options;

    /**
     * Número máximo de tentativas
     */
    public int $tries = 3;

    /**
     * Timeout em segundos
     */
    public int $timeout = 120;

    /**
     * Delay entre tentativas (segundos)
     */
    public int $backoff = 30;

    /**
     * Construtor
     */
    public function __construct(array $paymentData, array $options = [])
    {
        $this->paymentData = $paymentData;
        $this->options = array_merge([
            'notify_completion' => true,
            'retry_failed' => true,
            'webhook_url' => null,
            'metadata' => [],
        ], $options);

        // Configura queue baseado na prioridade
        $this->onQueue($this->determineQueue());
    }

    /**
     * Executa o job
     */
    public function handle(): void
    {
        Log::info('[Clubify Checkout] Iniciando processamento de pagamento', [
            'job_id' => $this->job->getJobId(),
            'payment_data' => $this->sanitizePaymentData(),
            'options' => $this->options,
        ]);

        try {
            $result = $this->processPayment();

            Log::info('[Clubify Checkout] Pagamento processado com sucesso', [
                'job_id' => $this->job->getJobId(),
                'transaction_id' => $result['transaction_id'] ?? null,
                'status' => $result['status'] ?? null,
            ]);

            $this->handleSuccess($result);

        } catch (PaymentException $e) {
            Log::error('[Clubify Checkout] Erro no processamento de pagamento', [
                'job_id' => $this->job->getJobId(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);

            $this->handlePaymentError($e);

        } catch (\Exception $e) {
            Log::error('[Clubify Checkout] Erro inesperado no processamento', [
                'job_id' => $this->job->getJobId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->handleGenericError($e);
        }
    }

    /**
     * Processa o pagamento
     */
    private function processPayment(): array
    {
        $sdk = ClubifyCheckout::getFacadeRoot();

        // Adiciona metadados do job
        $this->paymentData['metadata'] = array_merge(
            $this->paymentData['metadata'] ?? [],
            $this->options['metadata'],
            [
                'job_id' => $this->job->getJobId(),
                'processed_at' => now()->toISOString(),
                'processing_type' => 'async',
            ]
        );

        return $sdk->payments()->payment()->processPayment($this->paymentData);
    }

    /**
     * Trata sucesso no processamento
     */
    private function handleSuccess(array $result): void
    {
        // Dispara evento de sucesso
        event('clubify.checkout.payment.processed', [
            'job_id' => $this->job->getJobId(),
            'result' => $result,
            'options' => $this->options,
        ]);

        // Notifica webhook se configurado
        if ($this->options['webhook_url']) {
            dispatch(new SendWebhook(
                $this->options['webhook_url'],
                'payment.processed',
                $result
            ));
        }

        // Notifica conclusão se solicitado
        if ($this->options['notify_completion']) {
            $this->notifyCompletion($result);
        }
    }

    /**
     * Trata erro de pagamento
     */
    private function handlePaymentError(PaymentException $e): void
    {
        $shouldRetry = $this->shouldRetryPaymentError($e);

        if ($shouldRetry && $this->attempts() < $this->tries) {
            Log::info('[Clubify Checkout] Reagendando job devido a erro de pagamento', [
                'job_id' => $this->job->getJobId(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
            ]);

            $this->release($this->backoff * $this->attempts());
            return;
        }

        // Falha definitiva
        $this->handleFinalFailure($e);
    }

    /**
     * Trata erro genérico
     */
    private function handleGenericError(\Exception $e): void
    {
        if ($this->attempts() < $this->tries) {
            Log::info('[Clubify Checkout] Reagendando job devido a erro genérico', [
                'job_id' => $this->job->getJobId(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
            ]);

            $this->release($this->backoff * $this->attempts());
            return;
        }

        $this->handleFinalFailure($e);
    }

    /**
     * Trata falha definitiva
     */
    private function handleFinalFailure(\Exception $e): void
    {
        Log::error('[Clubify Checkout] Falha definitiva no processamento de pagamento', [
            'job_id' => $this->job->getJobId(),
            'attempts' => $this->attempts(),
            'error' => $e->getMessage(),
        ]);

        // Dispara evento de falha
        event('clubify.checkout.payment.failed', [
            'job_id' => $this->job->getJobId(),
            'payment_data' => $this->sanitizePaymentData(),
            'error' => $e->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Notifica webhook de falha se configurado
        if ($this->options['webhook_url']) {
            dispatch(new SendWebhook(
                $this->options['webhook_url'],
                'payment.failed',
                [
                    'payment_data' => $this->sanitizePaymentData(),
                    'error' => $e->getMessage(),
                    'attempts' => $this->attempts(),
                ]
            ));
        }

        $this->fail($e);
    }

    /**
     * Verifica se deve tentar novamente em caso de erro de pagamento
     */
    private function shouldRetryPaymentError(PaymentException $e): bool
    {
        if (!$this->options['retry_failed']) {
            return false;
        }

        // Códigos que indicam erro temporário (podem ser retentados)
        $retryableCodes = [
            'GATEWAY_TIMEOUT',
            'GATEWAY_UNAVAILABLE',
            'NETWORK_ERROR',
            'RATE_LIMIT_EXCEEDED',
            'TEMPORARY_FAILURE',
        ];

        return in_array($e->getCode(), $retryableCodes, true);
    }

    /**
     * Determina a queue baseado na prioridade
     */
    private function determineQueue(): string
    {
        $amount = $this->paymentData['amount'] ?? 0;

        // Pagamentos de alto valor vão para queue prioritária
        if ($amount >= 100000) { // R$ 1.000,00
            return 'payments-high';
        }

        // Pagamentos médios vão para queue normal
        if ($amount >= 10000) { // R$ 100,00
            return 'payments-normal';
        }

        // Pagamentos baixos vão para queue de baixa prioridade
        return 'payments-low';
    }

    /**
     * Sanitiza dados do pagamento para logging
     */
    private function sanitizePaymentData(): array
    {
        $sanitized = $this->paymentData;

        // Remove dados sensíveis
        $sensitiveFields = [
            'card_number',
            'cvv',
            'card_token',
            'bank_account',
            'cpf',
            'password',
            'secret',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Notifica conclusão do processamento
     */
    private function notifyCompletion(array $result): void
    {
        // Aqui poderia integrar com sistema de notificações
        // Por exemplo: email, SMS, push notification, etc.

        Log::info('[Clubify Checkout] Pagamento processado - notificação enviada', [
            'job_id' => $this->job->getJobId(),
            'transaction_id' => $result['transaction_id'] ?? null,
        ]);
    }

    /**
     * Método chamado quando o job falha definitivamente
     */
    public function failed(\Exception $exception): void
    {
        Log::error('[Clubify Checkout] Job de pagamento falhou definitivamente', [
            'job_id' => $this->job?->getJobId(),
            'exception' => $exception->getMessage(),
            'payment_data' => $this->sanitizePaymentData(),
        ]);
    }
}