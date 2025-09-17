<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Exceptions;

/**
 * Exception para erros de configuração de webhooks
 *
 * Representa erros relacionados à configuração
 * inválida ou incompatível de webhooks.
 */
class WebhookConfigException extends WebhookException
{
    /**
     * Cria exception de evento inválido
     */
    public static function invalidEvent(string $eventType, array $validEvents = []): self
    {
        $message = "Tipo de evento inválido: {$eventType}";

        if (!empty($validEvents)) {
            $message .= ". Eventos válidos: " . implode(', ', $validEvents);
        }

        return new self(
            $message,
            400,
            [
                'event_type' => $eventType,
                'valid_events' => $validEvents,
                'error_type' => 'invalid_event',
            ]
        );
    }

    /**
     * Cria exception de secret inválido
     */
    public static function invalidSecret(string $reason): self
    {
        return new self(
            "Secret de webhook inválido: {$reason}",
            400,
            [
                'reason' => $reason,
                'error_type' => 'invalid_secret',
            ]
        );
    }

    /**
     * Cria exception de timeout inválido
     */
    public static function invalidTimeout(int $timeout, int $min = 1, int $max = 300): self
    {
        return new self(
            "Timeout inválido: {$timeout}s. Deve estar entre {$min}s e {$max}s",
            400,
            [
                'timeout' => $timeout,
                'min_timeout' => $min,
                'max_timeout' => $max,
                'error_type' => 'invalid_timeout',
            ]
        );
    }

    /**
     * Cria exception de retry config inválida
     */
    public static function invalidRetryConfig(string $field, mixed $value, string $reason): self
    {
        return new self(
            "Configuração de retry inválida '{$field}': {$reason}",
            400,
            [
                'field' => $field,
                'value' => $value,
                'reason' => $reason,
                'error_type' => 'invalid_retry_config',
            ]
        );
    }

    /**
     * Cria exception de header inválido
     */
    public static function invalidHeader(string $headerName, string $reason): self
    {
        return new self(
            "Header inválido '{$headerName}': {$reason}",
            400,
            [
                'header_name' => $headerName,
                'reason' => $reason,
                'error_type' => 'invalid_header',
            ]
        );
    }

    /**
     * Cria exception de filtro inválido
     */
    public static function invalidFilter(array $filter, string $reason): self
    {
        return new self(
            "Filtro de evento inválido: {$reason}",
            400,
            [
                'filter' => $filter,
                'reason' => $reason,
                'error_type' => 'invalid_filter',
            ]
        );
    }

    /**
     * Cria exception de domínio bloqueado
     */
    public static function domainBlocked(string $domain): self
    {
        return new self(
            "Domínio bloqueado: {$domain}",
            403,
            [
                'domain' => $domain,
                'error_type' => 'domain_blocked',
            ]
        );
    }

    /**
     * Cria exception de IP bloqueado
     */
    public static function ipBlocked(string $ip): self
    {
        return new self(
            "IP bloqueado: {$ip}",
            403,
            [
                'ip' => $ip,
                'error_type' => 'ip_blocked',
            ]
        );
    }

    /**
     * Cria exception de HTTPS obrigatório
     */
    public static function httpsRequired(string $url): self
    {
        return new self(
            "HTTPS é obrigatório, URL fornecida usa HTTP: {$url}",
            400,
            [
                'url' => $url,
                'error_type' => 'https_required',
            ]
        );
    }

    /**
     * Cria exception de configuração conflitante
     */
    public static function conflictingConfig(string $field1, string $field2, string $reason): self
    {
        return new self(
            "Configuração conflitante entre '{$field1}' e '{$field2}': {$reason}",
            400,
            [
                'field1' => $field1,
                'field2' => $field2,
                'reason' => $reason,
                'error_type' => 'conflicting_config',
            ]
        );
    }

    /**
     * Cria exception de configuração obrigatória
     */
    public static function requiredConfig(string $field, string $reason = ''): self
    {
        $message = "Configuração obrigatória '{$field}' não fornecida";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self(
            $message,
            400,
            [
                'field' => $field,
                'reason' => $reason,
                'error_type' => 'required_config',
            ]
        );
    }

    /**
     * Cria exception de estratégia de retry inválida
     */
    public static function invalidRetryStrategy(string $strategy, array $validStrategies): self
    {
        return new self(
            "Estratégia de retry inválida: {$strategy}. Estratégias válidas: " . implode(', ', $validStrategies),
            400,
            [
                'strategy' => $strategy,
                'valid_strategies' => $validStrategies,
                'error_type' => 'invalid_retry_strategy',
            ]
        );
    }

    /**
     * Cria exception de algoritmo de assinatura inválido
     */
    public static function invalidSignatureAlgorithm(string $algorithm, array $validAlgorithms): self
    {
        return new self(
            "Algoritmo de assinatura inválido: {$algorithm}. Algoritmos válidos: " . implode(', ', $validAlgorithms),
            400,
            [
                'algorithm' => $algorithm,
                'valid_algorithms' => $validAlgorithms,
                'error_type' => 'invalid_signature_algorithm',
            ]
        );
    }
}