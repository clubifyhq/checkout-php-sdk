<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Tracking\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de evento de tracking
 *
 * Representa um evento individual de tracking com todas as informações
 * necessárias para análise e atribuição.
 *
 * Funcionalidades principais:
 * - Validação automática de dados
 * - Sanitização de metadados
 * - Controle de timestamp
 * - Versionamento de eventos
 * - Contexto de sessão e usuário
 *
 * Campos obrigatórios:
 * - event_type: Tipo do evento
 * - timestamp: Timestamp do evento
 * - session_id: ID da sessão
 *
 * Campos opcionais:
 * - user_id: ID do usuário
 * - page_url: URL da página
 * - referrer: URL de referência
 * - user_agent: User agent do browser
 * - metadata: Dados customizados do evento
 * - utm_params: Parâmetros UTM
 */
class EventData extends BaseData
{
    public string $event_type;
    public DateTime $timestamp;
    public string $session_id;
    public ?string $user_id = null;
    public ?string $page_url = null;
    public ?string $referrer = null;
    public ?string $user_agent = null;
    public array $metadata = [];
    public array $utm_params = [];
    public ?string $organization_id = null;
    public string $version = '1.0';
    public ?array $device_info = null;
    public ?array $geo_info = null;
    public ?string $ip_address = null;

    /**
     * Construtor com validação automática
     */
    public function __construct(array $data = [])
    {
        // Sanitizar dados antes de processar
        $data = $this->sanitizeData($data);
        
        parent::__construct($data);
        
        // Validar dados após construir
        $this->validate();
    }

    /**
     * Regras de validação
     */
    public function getRules(): array
    {
        return [
            'event_type' => ['required', 'string', 'min:1', 'max:100'],
            'timestamp' => ['required', 'date'],
            'session_id' => ['required', 'string', 'min:1'],
            'user_id' => ['nullable', 'string'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'user_agent' => ['nullable', 'string', 'max:1024'],
            'metadata' => ['array'],
            'utm_params' => ['array'],
            'organization_id' => ['nullable', 'string'],
            'version' => ['string'],
            'device_info' => ['nullable', 'array'],
            'geo_info' => ['nullable', 'array'],
            'ip_address' => ['nullable', 'string', 'max:45'],
        ];
    }

    /**
     * Sanitiza dados antes da validação
     */
    protected function sanitizeData(array $data): array
    {
        // Garantir timestamp
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = new DateTime();
        } elseif (is_string($data['timestamp'])) {
            $data['timestamp'] = new DateTime($data['timestamp']);
        }

        // Limpar URLs
        if (isset($data['page_url'])) {
            $data['page_url'] = filter_var($data['page_url'], FILTER_SANITIZE_URL);
        }

        if (isset($data['referrer'])) {
            $data['referrer'] = filter_var($data['referrer'], FILTER_SANITIZE_URL);
        }

        // Garantir arrays
        $data['metadata'] = $data['metadata'] ?? [];
        $data['utm_params'] = $data['utm_params'] ?? [];

        // Validar IP
        if (isset($data['ip_address'])) {
            $data['ip_address'] = filter_var($data['ip_address'], FILTER_VALIDATE_IP) ?: null;
        }

        return $data;
    }

    /**
     * Adiciona parâmetro UTM
     */
    public function addUtmParam(string $key, string $value): void
    {
        $this->utm_params[$key] = $value;
    }

    /**
     * Adiciona metadado
     */
    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * Define informações do dispositivo
     */
    public function setDeviceInfo(array $deviceInfo): void
    {
        $this->device_info = $deviceInfo;
    }

    /**
     * Define informações geográficas
     */
    public function setGeoInfo(array $geoInfo): void
    {
        $this->geo_info = $geoInfo;
    }

    /**
     * Verifica se é um evento de conversão
     */
    public function isConversionEvent(): bool
    {
        $conversionEvents = [
            'purchase_completed',
            'subscription_created',
            'trial_started',
            'signup_completed',
            'lead_generated'
        ];
        
        return in_array($this->event_type, $conversionEvents);
    }

    /**
     * Verifica se é um evento de engajamento
     */
    public function isEngagementEvent(): bool
    {
        $engagementEvents = [
            'page_view',
            'button_click',
            'form_interaction',
            'video_play',
            'scroll_depth',
            'time_on_page'
        ];
        
        return in_array($this->event_type, $engagementEvents);
    }

    /**
     * Obtém valor monetário do evento (se aplicável)
     */
    public function getEventValue(): ?float
    {
        return $this->metadata['value'] ?? $this->metadata['amount'] ?? null;
    }

    /**
     * Obtém fonte UTM
     */
    public function getUtmSource(): ?string
    {
        return $this->utm_params['utm_source'] ?? null;
    }

    /**
     * Obtém medium UTM
     */
    public function getUtmMedium(): ?string
    {
        return $this->utm_params['utm_medium'] ?? null;
    }

    /**
     * Obtém campanha UTM
     */
    public function getUtmCampaign(): ?string
    {
        return $this->utm_params['utm_campaign'] ?? null;
    }

    /**
     * Converte timestamp para formato ISO
     */
    public function getTimestampIso(): string
    {
        return $this->timestamp->format('c');
    }

    /**
     * Obtém dados mascarados para log
     */
    public function getMaskedData(): array
    {
        $data = $this->toArray();
        
        // Mascarar IP
        if (isset($data['ip_address'])) {
            $data['ip_address'] = $this->maskIpAddress($data['ip_address']);
        }
        
        // Mascarar user_agent
        if (isset($data['user_agent'])) {
            $data['user_agent'] = substr($data['user_agent'], 0, 50) . '...';
        }
        
        // Remover metadados sensíveis
        if (isset($data['metadata'])) {
            $data['metadata'] = $this->removeSensitiveMetadata($data['metadata']);
        }
        
        return $data;
    }

    /**
     * Exporta dados para analytics
     */
    public function toAnalyticsFormat(): array
    {
        return [
            'event' => $this->event_type,
            'timestamp' => $this->getTimestampIso(),
            'session_id' => $this->session_id,
            'user_id' => $this->user_id,
            'page_url' => $this->page_url,
            'referrer' => $this->referrer,
            'utm_source' => $this->getUtmSource(),
            'utm_medium' => $this->getUtmMedium(),
            'utm_campaign' => $this->getUtmCampaign(),
            'event_value' => $this->getEventValue(),
            'is_conversion' => $this->isConversionEvent(),
            'is_engagement' => $this->isEngagementEvent(),
            'device_info' => $this->device_info,
            'geo_info' => $this->geo_info,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Mascara endereço IP
     */
    private function maskIpAddress(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.***.***.***';
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            return $parts[0] . ':' . $parts[1] . ':****:****:****:****:****:****';
        }
        
        return '***.***.***';
    }

    /**
     * Remove metadados sensíveis
     */
    private function removeSensitiveMetadata(array $metadata): array
    {
        $sensitiveKeys = [
            'password', 'token', 'secret', 'key', 'api_key',
            'credit_card', 'ssn', 'cpf', 'cnpj', 'passport'
        ];

        foreach ($sensitiveKeys as $key) {
            if (isset($metadata[$key])) {
                $metadata[$key] = '[REDACTED]';
            }
        }

        return $metadata;
    }
}
