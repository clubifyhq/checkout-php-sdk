<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Middleware;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Utils\Crypto\HMACSignature;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware para validação de webhooks
 */
final class ValidateWebhook
{
    /**
     * SDK instance
     */
    private ClubifyCheckoutSDK $sdk;

    /**
     * HMAC signature validator
     */
    private HMACSignature $hmac;

    /**
     * Construtor
     */
    public function __construct(ClubifyCheckoutSDK $sdk, HMACSignature $hmac)
    {
        $this->sdk = $sdk;
        $this->hmac = $hmac;
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next, string $secret = null): SymfonyResponse
    {
        try {
            // Valida assinatura do webhook
            $this->validateSignature($request, $secret);

            // Valida timestamp para prevenir replay attacks
            $this->validateTimestamp($request);

            // Valida formato do payload
            $this->validatePayload($request);

            // Enriquece request com dados do webhook
            $this->enrichRequest($request);

            return $next($request);

        } catch (\Exception $e) {
            return $this->invalidWebhookResponse($e->getMessage());
        }
    }

    /**
     * Valida assinatura HMAC do webhook
     */
    private function validateSignature(Request $request, ?string $secret): void
    {
        $signature = $request->header('X-Clubify-Signature');
        if (!$signature) {
            throw new \InvalidArgumentException('Assinatura do webhook não encontrada');
        }

        $payload = $request->getContent();
        if (empty($payload)) {
            throw new \InvalidArgumentException('Payload do webhook vazio');
        }

        // Usa secret fornecido ou busca do SDK
        $webhookSecret = $secret ?? $this->getWebhookSecret();

        if (!$this->hmac->verify($payload, $signature, $webhookSecret)) {
            throw new \InvalidArgumentException('Assinatura do webhook inválida');
        }
    }

    /**
     * Valida timestamp para prevenir replay attacks
     */
    private function validateTimestamp(Request $request): void
    {
        $timestamp = $request->header('X-Clubify-Timestamp');
        if (!$timestamp) {
            throw new \InvalidArgumentException('Timestamp do webhook não encontrado');
        }

        // Converte para timestamp se for string
        if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        }

        $currentTime = time();
        $tolerance = 300; // 5 minutos

        // Verifica se não é muito antigo
        if (($currentTime - $timestamp) > $tolerance) {
            throw new \InvalidArgumentException('Webhook expirado - timestamp muito antigo');
        }

        // Verifica se não é do futuro
        if ($timestamp > ($currentTime + $tolerance)) {
            throw new \InvalidArgumentException('Webhook inválido - timestamp do futuro');
        }
    }

    /**
     * Valida formato do payload
     */
    private function validatePayload(Request $request): void
    {
        $payload = $request->getContent();

        // Verifica se é JSON válido
        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Payload JSON inválido: ' . json_last_error_msg());
        }

        // Verifica estrutura básica
        $this->validatePayloadStructure($decoded);

        // Adiciona payload decodificado ao request
        $request->attributes->set('webhook_payload', $decoded);
    }

    /**
     * Valida estrutura básica do payload
     */
    private function validatePayloadStructure(array $payload): void
    {
        $requiredFields = ['event', 'data', 'timestamp'];

        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }
        }

        // Valida tipo do evento
        if (!is_string($payload['event']) || empty($payload['event'])) {
            throw new \InvalidArgumentException('Tipo de evento inválido');
        }

        // Valida formato do timestamp
        if (!is_numeric($payload['timestamp']) && !strtotime($payload['timestamp'])) {
            throw new \InvalidArgumentException('Timestamp inválido no payload');
        }
    }

    /**
     * Enriquece request com dados do webhook
     */
    private function enrichRequest(Request $request): void
    {
        $payload = $request->attributes->get('webhook_payload');

        // Adiciona informações do evento
        $request->attributes->set('webhook_event', $payload['event']);
        $request->attributes->set('webhook_data', $payload['data']);
        $request->attributes->set('webhook_timestamp', $payload['timestamp']);

        // Adiciona ID único do webhook se presente
        if (isset($payload['id'])) {
            $request->attributes->set('webhook_id', $payload['id']);
        }

        // Adiciona informações de origem
        $request->attributes->set('webhook_source', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->getRelevantHeaders($request),
        ]);

        // Adiciona metadados para processamento
        $request->attributes->set('webhook_metadata', [
            'received_at' => now()->toISOString(),
            'processing_started_at' => microtime(true),
            'sdk_version' => $this->sdk->getVersion(),
        ]);
    }

    /**
     * Obtém o webhook secret da organização (suporte multi-tenant)
     *
     * Tenta obter o organization_id de múltiplas fontes:
     * 1. Header X-Organization-ID (prioridade)
     * 2. Payload JSON (fallback)
     *
     * Em seguida, permite que a aplicação customize como buscar o secret:
     * - Via callback configurado
     * - Via Model Organization (se existir)
     * - Via config global (fallback para single-tenant)
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    private function getWebhookSecret(): string
    {
        // Primeiro: verificar se há callback customizado configurado
        $callback = $this->sdk->getConfig()->get('webhook.secret_resolver');
        if (is_callable($callback)) {
            try {
                $secret = $callback(request());
                if ($secret && is_string($secret)) {
                    return $secret;
                }
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Erro ao executar webhook.secret_resolver: ' . $e->getMessage());
            }
        }

        // Segundo: tentar obter organization_id e buscar secret da organização
        $organizationId = $this->getOrganizationId();
        if ($organizationId) {
            $secret = $this->getOrganizationSecret($organizationId);
            if ($secret) {
                return $secret;
            }
        }

        // Terceiro: fallback para config global (single-tenant)
        $webhookSecret = $this->sdk->getConfig()->get('webhook.secret');
        if ($webhookSecret) {
            return $webhookSecret;
        }

        throw new \InvalidArgumentException('Webhook secret não configurado. Configure via webhook.secret_resolver, model Organization, ou webhook.secret');
    }

    /**
     * Obtém organization_id da requisição
     *
     * @return string|null
     */
    private function getOrganizationId(): ?string
    {
        // Prioridade 1: Header
        $organizationId = request()->header('X-Organization-ID');
        if ($organizationId) {
            return $organizationId;
        }

        // Prioridade 2: Payload JSON
        try {
            $payload = json_decode(request()->getContent(), true);
            return $payload['data']['organization_id']
                ?? $payload['organization_id']
                ?? $payload['data']['organizationId']
                ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtém webhook secret da organização usando Model
     *
     * @param string $organizationId
     * @return string|null
     */
    private function getOrganizationSecret(string $organizationId): ?string
    {
        // Verificar se Model Organization existe (Laravel)
        $organizationClass = $this->sdk->getConfig()->get('webhook.organization_model', '\\App\\Models\\Organization');

        if (!class_exists($organizationClass)) {
            return null;
        }

        try {
            $organization = $organizationClass::find($organizationId);
            if (!$organization) {
                return null;
            }

            // Tentar múltiplos locais onde o secret pode estar
            $secretKey = $this->sdk->getConfig()->get('webhook.organization_secret_key', 'clubify_checkout_webhook_secret');

            // Opção 1: $organization->settings['clubify_checkout_webhook_secret']
            if (isset($organization->settings) && is_array($organization->settings)) {
                return $organization->settings[$secretKey] ?? null;
            }

            // Opção 2: $organization->webhook_secret (campo direto)
            if (isset($organization->webhook_secret)) {
                return $organization->webhook_secret;
            }

            // Opção 3: $organization->clubify_checkout_webhook_secret
            if (isset($organization->{$secretKey})) {
                return $organization->{$secretKey};
            }

            return null;
        } catch (\Exception $e) {
            // Se falhar ao buscar organização, retorna null para tentar fallback
            return null;
        }
    }

    /**
     * Obtém headers relevantes para logging
     */
    private function getRelevantHeaders(Request $request): array
    {
        $relevantHeaders = [
            'X-Clubify-Signature',
            'X-Clubify-Timestamp',
            'X-Clubify-Event',
            'X-Forwarded-For',
            'Content-Type',
            'Content-Length',
        ];

        $headers = [];
        foreach ($relevantHeaders as $header) {
            $value = $request->header($header);
            if ($value) {
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Resposta para webhook inválido
     */
    private function invalidWebhookResponse(string $message): JsonResponse
    {
        $data = [
            'error' => 'Invalid Webhook',
            'message' => $message,
            'code' => 400,
            'timestamp' => now()->toISOString(),
        ];

        return response()->json($data, 400);
    }
}
