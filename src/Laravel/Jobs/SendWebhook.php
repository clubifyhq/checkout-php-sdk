<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Jobs;

use Clubify\Checkout\Laravel\Facades\ClubifyCheckout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job para envio assíncrono de webhooks
 */
final class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * URL do webhook
     */
    private string $url;

    /**
     * Evento do webhook
     */
    private string $event;

    /**
     * Dados do webhook
     */
    private array $data;

    /**
     * Opções de envio
     */
    private array $options;

    /**
     * Número máximo de tentativas
     */
    public int $tries = 5;

    /**
     * Timeout em segundos
     */
    public int $timeout = 30;

    /**
     * Delay entre tentativas (segundos)
     */
    public int $backoff = 60;

    /**
     * Construtor
     */
    public function __construct(string $url, string $event, array $data, array $options = [])
    {
        $this->url = $url;
        $this->event = $event;
        $this->data = $data;
        $this->options = array_merge([
            'timeout' => 30,
            'retry_delay' => 60,
            'verify_ssl' => true,
            'headers' => [],
            'signature_secret' => null,
        ], $options);

        $this->onQueue('webhooks');
    }

    /**
     * Executa o job
     */
    public function handle(): void
    {
        Log::info('[Clubify Checkout] Enviando webhook', [
            'job_id' => $this->job->getJobId(),
            'url' => $this->url,
            'event' => $this->event,
            'attempt' => $this->attempts(),
        ]);

        try {
            $response = $this->sendWebhook();

            Log::info('[Clubify Checkout] Webhook enviado com sucesso', [
                'job_id' => $this->job->getJobId(),
                'url' => $this->url,
                'event' => $this->event,
                'status_code' => $response['status_code'],
                'response_time' => $response['response_time'],
            ]);

            $this->handleSuccess($response);

        } catch (\Exception $e) {
            Log::error('[Clubify Checkout] Erro no envio de webhook', [
                'job_id' => $this->job->getJobId(),
                'url' => $this->url,
                'event' => $this->event,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            $this->handleError($e);
        }
    }

    /**
     * Envia o webhook
     */
    private function sendWebhook(): array
    {
        $payload = $this->buildPayload();
        $headers = $this->buildHeaders($payload);

        $startTime = microtime(true);

        $response = Http::withHeaders($headers)
            ->timeout($this->options['timeout'])
            ->withOptions([
                'verify' => $this->options['verify_ssl'],
            ])
            ->post($this->url, $payload);

        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Webhook failed with status {$response->status()}: {$response->body()}"
            );
        }

        return [
            'status_code' => $response->status(),
            'response_time' => $responseTime,
            'response_body' => $response->body(),
            'headers' => $response->headers(),
        ];
    }

    /**
     * Constrói payload do webhook
     */
    private function buildPayload(): array
    {
        return [
            'id' => uniqid('webhook_', true),
            'event' => $this->event,
            'data' => $this->data,
            'timestamp' => now()->timestamp,
            'metadata' => [
                'job_id' => $this->job->getJobId(),
                'attempt' => $this->attempts(),
                'sdk_version' => ClubifyCheckout::getVersion(),
                'environment' => app()->environment(),
            ],
        ];
    }

    /**
     * Constrói headers do webhook
     */
    private function buildHeaders(array $payload): array
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Clubify-Checkout-SDK-PHP/' . ClubifyCheckout::getVersion(),
            'X-Clubify-Event' => $this->event,
            'X-Clubify-Timestamp' => (string) $payload['timestamp'],
            'X-Clubify-Webhook-Id' => $payload['id'],
        ], $this->options['headers']);

        // Adiciona assinatura se secret fornecido
        if ($this->options['signature_secret']) {
            $signature = $this->generateSignature($payload);
            $headers['X-Clubify-Signature'] = $signature;
        }

        return $headers;
    }

    /**
     * Gera assinatura HMAC do payload
     */
    private function generateSignature(array $payload): string
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $payloadJson, $this->options['signature_secret']);

        return 'sha256=' . $signature;
    }

    /**
     * Trata sucesso no envio
     */
    private function handleSuccess(array $response): void
    {
        // Registra sucesso no SDK
        try {
            ClubifyCheckout::webhooks()->delivery()->recordSuccess(
                $this->url,
                $this->event,
                $response['response_time']
            );
        } catch (\Exception $e) {
            Log::warning('[Clubify Checkout] Falha ao registrar sucesso do webhook', [
                'error' => $e->getMessage(),
            ]);
        }

        // Dispara evento de sucesso
        event('clubify.checkout.webhook.sent', [
            'job_id' => $this->job->getJobId(),
            'url' => $this->url,
            'event' => $this->event,
            'response' => $response,
        ]);
    }

    /**
     * Trata erro no envio
     */
    private function handleError(\Exception $e): void
    {
        $shouldRetry = $this->shouldRetry($e);

        if ($shouldRetry && $this->attempts() < $this->tries) {
            $delay = $this->calculateRetryDelay();

            Log::info('[Clubify Checkout] Reagendando webhook', [
                'job_id' => $this->job->getJobId(),
                'url' => $this->url,
                'event' => $this->event,
                'attempt' => $this->attempts(),
                'delay' => $delay,
            ]);

            $this->release($delay);
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

        // Não tenta novamente para erros 4xx (exceto 408, 429)
        if (preg_match('/failed with status (4\d\d)/', $message, $matches)) {
            $statusCode = (int) $matches[1];
            return in_array($statusCode, [408, 429], true);
        }

        // Tenta novamente para erros 5xx e timeouts
        return true;
    }

    /**
     * Calcula delay para retry com backoff exponencial
     */
    private function calculateRetryDelay(): int
    {
        $baseDelay = $this->options['retry_delay'];
        $attempt = $this->attempts();

        // Backoff exponencial com jitter
        $delay = $baseDelay * pow(2, $attempt - 1);
        $jitter = rand(0, (int) ($delay * 0.1));

        return min($delay + $jitter, 3600); // Máximo 1 hora
    }

    /**
     * Trata falha definitiva
     */
    private function handleFinalFailure(\Exception $e): void
    {
        Log::error('[Clubify Checkout] Falha definitiva no envio de webhook', [
            'job_id' => $this->job->getJobId(),
            'url' => $this->url,
            'event' => $this->event,
            'attempts' => $this->attempts(),
            'error' => $e->getMessage(),
        ]);

        // Registra falha no SDK
        try {
            ClubifyCheckout::webhooks()->delivery()->recordFailure(
                $this->url,
                $this->event,
                $e->getMessage()
            );
        } catch (\Exception $registrationError) {
            Log::warning('[Clubify Checkout] Falha ao registrar erro do webhook', [
                'error' => $registrationError->getMessage(),
            ]);
        }

        // Dispara evento de falha
        event('clubify.checkout.webhook.failed', [
            'job_id' => $this->job->getJobId(),
            'url' => $this->url,
            'event' => $this->event,
            'error' => $e->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->fail($e);
    }

    /**
     * Método chamado quando o job falha definitivamente
     */
    public function failed(\Exception $exception): void
    {
        Log::error('[Clubify Checkout] Job de webhook falhou definitivamente', [
            'job_id' => $this->job?->getJobId(),
            'url' => $this->url,
            'event' => $this->event,
            'exception' => $exception->getMessage(),
        ]);
    }
}