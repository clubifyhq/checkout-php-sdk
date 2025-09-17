<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Exceptions;

use Clubify\Checkout\Core\Exceptions\BaseException;

/**
 * Exception base para webhooks
 *
 * Classe base para todas as exceções relacionadas
 * ao sistema de webhooks.
 */
class WebhookException extends BaseException
{
    protected string $module = 'webhooks';

    /**
     * Cria exception de webhook não encontrado
     */
    public static function notFound(string $webhookId): self
    {
        return new self(
            "Webhook não encontrado: {$webhookId}",
            404,
            [
                'webhook_id' => $webhookId,
                'error_type' => 'webhook_not_found',
            ]
        );
    }

    /**
     * Cria exception de URL inválida
     */
    public static function invalidUrl(string $url, string $reason = ''): self
    {
        $message = "URL de webhook inválida: {$url}";
        if ($reason) {
            $message .= " ({$reason})";
        }

        return new self(
            $message,
            400,
            [
                'url' => $url,
                'reason' => $reason,
                'error_type' => 'invalid_url',
            ]
        );
    }

    /**
     * Cria exception de configuração inválida
     */
    public static function invalidConfig(string $field, string $reason): self
    {
        return new self(
            "Configuração inválida para '{$field}': {$reason}",
            400,
            [
                'field' => $field,
                'reason' => $reason,
                'error_type' => 'invalid_config',
            ]
        );
    }

    /**
     * Cria exception de webhook desativado
     */
    public static function webhookDisabled(string $webhookId): self
    {
        return new self(
            "Webhook está desativado: {$webhookId}",
            403,
            [
                'webhook_id' => $webhookId,
                'error_type' => 'webhook_disabled',
            ]
        );
    }

    /**
     * Cria exception de limite de webhooks excedido
     */
    public static function limitExceeded(int $current, int $limit): self
    {
        return new self(
            "Limite de webhooks excedido: {$current}/{$limit}",
            429,
            [
                'current_count' => $current,
                'limit' => $limit,
                'error_type' => 'limit_exceeded',
            ]
        );
    }

    /**
     * Cria exception de evento não suportado
     */
    public static function unsupportedEvent(string $eventType): self
    {
        return new self(
            "Tipo de evento não suportado: {$eventType}",
            400,
            [
                'event_type' => $eventType,
                'error_type' => 'unsupported_event',
            ]
        );
    }

    /**
     * Cria exception de permissão negada
     */
    public static function accessDenied(string $webhookId, string $action = 'access'): self
    {
        return new self(
            "Acesso negado para {$action} do webhook: {$webhookId}",
            403,
            [
                'webhook_id' => $webhookId,
                'action' => $action,
                'error_type' => 'access_denied',
            ]
        );
    }
}