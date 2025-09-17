<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Jobs;

use Clubify\Checkout\Laravel\Facades\ClubifyCheckout;
use Clubify\Checkout\Modules\Customers\DTOs\CustomerData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para sincronização assíncrona de clientes
 */
final class SyncCustomer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Dados do cliente
     */
    private array $customerData;

    /**
     * Tipo de operação
     */
    private string $operation;

    /**
     * Opções de sincronização
     */
    private array $options;

    /**
     * Número máximo de tentativas
     */
    public int $tries = 3;

    /**
     * Timeout em segundos
     */
    public int $timeout = 60;

    /**
     * Delay entre tentativas (segundos)
     */
    public int $backoff = 30;

    /**
     * Construtor
     */
    public function __construct(array $customerData, string $operation = 'sync', array $options = [])
    {
        $this->customerData = $customerData;
        $this->operation = $operation;
        $this->options = array_merge([
            'force_update' => false,
            'merge_data' => true,
            'update_history' => true,
            'update_profile' => true,
            'notify_completion' => false,
        ], $options);

        $this->onQueue('customers');
    }

    /**
     * Executa o job
     */
    public function handle(): void
    {
        Log::info('[Clubify Checkout] Iniciando sincronização de cliente', [
            'job_id' => $this->job->getJobId(),
            'operation' => $this->operation,
            'customer_id' => $this->customerData['id'] ?? null,
            'email' => $this->customerData['email'] ?? null,
        ]);

        try {
            $result = match ($this->operation) {
                'create' => $this->createCustomer(),
                'update' => $this->updateCustomer(),
                'sync' => $this->syncCustomer(),
                'merge' => $this->mergeCustomer(),
                'delete' => $this->deleteCustomer(),
                default => throw new \InvalidArgumentException("Operação inválida: {$this->operation}"),
            };

            Log::info('[Clubify Checkout] Cliente sincronizado com sucesso', [
                'job_id' => $this->job->getJobId(),
                'operation' => $this->operation,
                'customer_id' => $result['id'] ?? null,
            ]);

            $this->handleSuccess($result);

        } catch (\Exception $e) {
            Log::error('[Clubify Checkout] Erro na sincronização de cliente', [
                'job_id' => $this->job->getJobId(),
                'operation' => $this->operation,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            $this->handleError($e);
        }
    }

    /**
     * Cria novo cliente
     */
    private function createCustomer(): array
    {
        $customerData = CustomerData::fromArray($this->customerData);
        return ClubifyCheckout::customers()->customer()->create($customerData->toArray());
    }

    /**
     * Atualiza cliente existente
     */
    private function updateCustomer(): array
    {
        $customerId = $this->customerData['id'] ?? throw new \InvalidArgumentException('ID do cliente é obrigatório para atualização');

        $customerData = CustomerData::fromArray($this->customerData);
        return ClubifyCheckout::customers()->customer()->update($customerId, $customerData->toArray());
    }

    /**
     * Sincroniza cliente (find or create)
     */
    private function syncCustomer(): array
    {
        $customerData = CustomerData::fromArray($this->customerData);
        return ClubifyCheckout::customers()->findOrCreateCustomer($customerData->toArray());
    }

    /**
     * Faz merge de dados do cliente
     */
    private function mergeCustomer(): array
    {
        $customerId = $this->customerData['id'] ?? throw new \InvalidArgumentException('ID do cliente é obrigatório para merge');

        return ClubifyCheckout::customers()->matching()->mergeCustomerData(
            $customerId,
            $this->customerData,
            $this->options
        );
    }

    /**
     * Remove cliente
     */
    private function deleteCustomer(): array
    {
        $customerId = $this->customerData['id'] ?? throw new \InvalidArgumentException('ID do cliente é obrigatório para remoção');

        return ClubifyCheckout::customers()->customer()->delete($customerId);
    }

    /**
     * Trata sucesso na operação
     */
    private function handleSuccess(array $result): void
    {
        // Atualiza histórico se solicitado
        if ($this->options['update_history'] && isset($result['id'])) {
            $this->updateCustomerHistory($result['id']);
        }

        // Atualiza perfil se solicitado
        if ($this->options['update_profile'] && isset($result['id'])) {
            $this->updateCustomerProfile($result['id']);
        }

        // Dispara evento de sucesso
        event('clubify.checkout.customer.synced', [
            'job_id' => $this->job->getJobId(),
            'operation' => $this->operation,
            'result' => $result,
            'options' => $this->options,
        ]);

        // Notifica conclusão se solicitado
        if ($this->options['notify_completion']) {
            $this->notifyCompletion($result);
        }
    }

    /**
     * Atualiza histórico do cliente
     */
    private function updateCustomerHistory(string $customerId): void
    {
        try {
            ClubifyCheckout::customers()->history()->updateHistory($customerId);

            Log::info('[Clubify Checkout] Histórico do cliente atualizado', [
                'job_id' => $this->job->getJobId(),
                'customer_id' => $customerId,
            ]);
        } catch (\Exception $e) {
            Log::warning('[Clubify Checkout] Falha ao atualizar histórico do cliente', [
                'job_id' => $this->job->getJobId(),
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Atualiza perfil do cliente
     */
    private function updateCustomerProfile(string $customerId): void
    {
        try {
            ClubifyCheckout::customers()->profile()->updateProfile($customerId);

            Log::info('[Clubify Checkout] Perfil do cliente atualizado', [
                'job_id' => $this->job->getJobId(),
                'customer_id' => $customerId,
            ]);
        } catch (\Exception $e) {
            Log::warning('[Clubify Checkout] Falha ao atualizar perfil do cliente', [
                'job_id' => $this->job->getJobId(),
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trata erro na operação
     */
    private function handleError(\Exception $e): void
    {
        $shouldRetry = $this->shouldRetry($e);

        if ($shouldRetry && $this->attempts() < $this->tries) {
            Log::info('[Clubify Checkout] Reagendando sincronização de cliente', [
                'job_id' => $this->job->getJobId(),
                'operation' => $this->operation,
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
     * Verifica se deve tentar novamente
     */
    private function shouldRetry(\Exception $e): bool
    {
        $message = $e->getMessage();

        // Não tenta novamente para erros de validação
        $nonRetryableErrors = [
            'Validation failed',
            'Invalid data',
            'Customer not found',
            'Duplicate customer',
        ];

        foreach ($nonRetryableErrors as $error) {
            if (str_contains($message, $error)) {
                return false;
            }
        }

        // Tenta novamente para outros erros
        return true;
    }

    /**
     * Trata falha definitiva
     */
    private function handleFinalFailure(\Exception $e): void
    {
        Log::error('[Clubify Checkout] Falha definitiva na sincronização de cliente', [
            'job_id' => $this->job->getJobId(),
            'operation' => $this->operation,
            'customer_data' => $this->sanitizeCustomerData(),
            'attempts' => $this->attempts(),
            'error' => $e->getMessage(),
        ]);

        // Dispara evento de falha
        event('clubify.checkout.customer.sync_failed', [
            'job_id' => $this->job->getJobId(),
            'operation' => $this->operation,
            'customer_data' => $this->sanitizeCustomerData(),
            'error' => $e->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->fail($e);
    }

    /**
     * Sanitiza dados do cliente para logging
     */
    private function sanitizeCustomerData(): array
    {
        $sanitized = $this->customerData;

        // Remove dados sensíveis
        $sensitiveFields = [
            'password',
            'cpf',
            'ssn',
            'tax_id',
            'credit_card',
            'bank_account',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Notifica conclusão da sincronização
     */
    private function notifyCompletion(array $result): void
    {
        // Aqui poderia integrar com sistema de notificações
        Log::info('[Clubify Checkout] Cliente sincronizado - notificação enviada', [
            'job_id' => $this->job->getJobId(),
            'customer_id' => $result['id'] ?? null,
            'operation' => $this->operation,
        ]);
    }

    /**
     * Factory methods para diferentes operações
     */
    public static function create(array $customerData, array $options = []): self
    {
        return new self($customerData, 'create', $options);
    }

    public static function update(array $customerData, array $options = []): self
    {
        return new self($customerData, 'update', $options);
    }

    public static function sync(array $customerData, array $options = []): self
    {
        return new self($customerData, 'sync', $options);
    }

    public static function merge(array $customerData, array $options = []): self
    {
        return new self($customerData, 'merge', $options);
    }

    public static function delete(array $customerData, array $options = []): self
    {
        return new self($customerData, 'delete', $options);
    }

    /**
     * Método chamado quando o job falha definitivamente
     */
    public function failed(\Exception $exception): void
    {
        Log::error('[Clubify Checkout] Job de sincronização de cliente falhou definitivamente', [
            'job_id' => $this->job?->getJobId(),
            'operation' => $this->operation,
            'exception' => $exception->getMessage(),
            'customer_data' => $this->sanitizeCustomerData(),
        ]);
    }
}