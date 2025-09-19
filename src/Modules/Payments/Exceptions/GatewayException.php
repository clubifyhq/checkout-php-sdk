<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Exceptions;

use Exception;

/**
 * Gateway Exception
 *
 * Exception lançada quando ocorrem erros relacionados a gateways de pagamento:
 * - Gateway indisponível
 * - Gateway não encontrado
 * - Falhas de comunicação com gateway
 * - Configuração inválida de gateway
 *
 * @package Clubify\Checkout\Modules\Payments\Exceptions
 * @version 1.0.0
 * @author Clubify Checkout Team
 */
class GatewayException extends Exception
{
    /**
     * Create exception for gateway not found
     */
    public static function notFound(string $gatewayName): static
    {
        return new static("Gateway '{$gatewayName}' not found");
    }

    /**
     * Create exception for gateway unavailable
     */
    public static function unavailable(string $gatewayName): static
    {
        return new static("Gateway '{$gatewayName}' is currently unavailable");
    }

    /**
     * Create exception for gateway configuration error
     */
    public static function configurationError(string $gatewayName, string $reason): static
    {
        return new static("Gateway '{$gatewayName}' configuration error: {$reason}");
    }

    /**
     * Create exception for gateway communication failure
     */
    public static function communicationFailure(string $gatewayName, string $reason): static
    {
        return new static("Gateway '{$gatewayName}' communication failure: {$reason}");
    }

    /**
     * Create exception for no available gateways
     */
    public static function noAvailableGateways(string $paymentMethod, string $currency): static
    {
        return new static("No available gateways for payment method '{$paymentMethod}' in currency '{$currency}'");
    }

    /**
     * Create exception for circuit breaker open
     */
    public static function circuitBreakerOpen(string $gatewayName): static
    {
        return new static("Gateway '{$gatewayName}' circuit breaker is open - too many failures");
    }
}