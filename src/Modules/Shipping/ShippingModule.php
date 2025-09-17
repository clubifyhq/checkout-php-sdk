<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Shipping;

use Clubify\Checkout\Core\BaseModule;

/**
 * Módulo Shipping
 *
 * Gerencia cálculos de frete, métodos de envio, rastreamento
 * e integração com transportadoras nacionais e internacionais.
 */
class ShippingModule extends BaseModule
{
    /**
     * Calcula opções de frete para um pedido
     */
    public function calculateShipping(string $orderId, array $destination): array
    {
        $endpoint = "/shipping/calculate/{$orderId}";
        return $this->httpClient->post($endpoint, $destination);
    }

    /**
     * Agenda o envio de um pedido
     */
    public function scheduleShipping(string $orderId, array $options): array
    {
        $endpoint = "/shipping/schedule/{$orderId}";
        return $this->httpClient->post($endpoint, $options);
    }

    /**
     * Rastreia um envio pelo código de rastreamento
     */
    public function trackShipment(string $trackingCode): array
    {
        $endpoint = "/shipping/track/{$trackingCode}";
        return $this->httpClient->get($endpoint);
    }

    /**
     * Obtém o status de um envio
     */
    public function getShipmentStatus(string $shipmentId): array
    {
        $endpoint = "/shipping/shipments/{$shipmentId}/status";
        return $this->httpClient->get($endpoint);
    }

    /**
     * Lista métodos de envio disponíveis
     */
    public function getShippingMethods(): array
    {
        $endpoint = '/shipping/methods';
        return $this->httpClient->get($endpoint);
    }

    /**
     * Obtém métodos de envio para uma localização específica
     */
    public function getShippingMethodsForLocation(array $location): array
    {
        $endpoint = '/shipping/methods/location';
        return $this->httpClient->post($endpoint, $location);
    }

    /**
     * Cria uma etiqueta de envio
     */
    public function createShippingLabel(string $orderId, array $options = []): array
    {
        $endpoint = "/shipping/labels/{$orderId}";
        return $this->httpClient->post($endpoint, $options);
    }

    /**
     * Obtém uma etiqueta de envio
     */
    public function getShippingLabel(string $labelId): array
    {
        $endpoint = "/shipping/labels/{$labelId}";
        return $this->httpClient->get($endpoint);
    }

    /**
     * Cancela um envio
     */
    public function cancelShipment(string $shipmentId, string $reason = ''): bool
    {
        $endpoint = "/shipping/shipments/{$shipmentId}/cancel";
        $response = $this->httpClient->post($endpoint, ['reason' => $reason]);
        return $response['success'] ?? false;
    }

    /**
     * Atualiza informações de rastreamento
     */
    public function updateTrackingInfo(string $shipmentId, array $trackingData): bool
    {
        $endpoint = "/shipping/shipments/{$shipmentId}/tracking";
        $response = $this->httpClient->put($endpoint, $trackingData);
        return $response['success'] ?? false;
    }

    /**
     * Obtém histórico de rastreamento
     */
    public function getTrackingHistory(string $trackingCode): array
    {
        $endpoint = "/shipping/tracking/{$trackingCode}/history";
        return $this->httpClient->get($endpoint);
    }

    /**
     * Calcula prazo de entrega
     */
    public function calculateDeliveryTime(array $origin, array $destination, string $serviceType = 'standard'): array
    {
        $endpoint = '/shipping/delivery-time';

        $data = [
            'origin' => $origin,
            'destination' => $destination,
            'service_type' => $serviceType
        ];

        return $this->httpClient->post($endpoint, $data);
    }

    /**
     * Valida um endereço
     */
    public function validateAddress(array $address): array
    {
        $endpoint = '/shipping/address/validate';
        return $this->httpClient->post($endpoint, $address);
    }

    /**
     * Normaliza um endereço
     */
    public function normalizeAddress(array $address): array
    {
        $endpoint = '/shipping/address/normalize';
        return $this->httpClient->post($endpoint, $address);
    }

    /**
     * Obtém informações de CEP
     */
    public function getPostalCodeInfo(string $postalCode): array
    {
        $endpoint = "/shipping/postal-code/{$postalCode}";
        return $this->httpClient->get($endpoint);
    }

    /**
     * Configura transportadoras
     */
    public function configureCarriers(array $carriers): bool
    {
        $endpoint = '/shipping/carriers/configure';
        $response = $this->httpClient->post($endpoint, $carriers);
        return $response['success'] ?? false;
    }

    /**
     * Lista transportadoras disponíveis
     */
    public function getAvailableCarriers(): array
    {
        $endpoint = '/shipping/carriers';
        return $this->httpClient->get($endpoint);
    }

    /**
     * Obtém configuração de uma transportadora
     */
    public function getCarrierConfig(string $carrierId): array
    {
        $endpoint = "/shipping/carriers/{$carrierId}/config";
        return $this->httpClient->get($endpoint);
    }

    /**
     * Atualiza configuração de uma transportadora
     */
    public function updateCarrierConfig(string $carrierId, array $config): bool
    {
        $endpoint = "/shipping/carriers/{$carrierId}/config";
        $response = $this->httpClient->put($endpoint, $config);
        return $response['success'] ?? false;
    }

    /**
     * Testa conectividade com uma transportadora
     */
    public function testCarrierConnection(string $carrierId): array
    {
        $endpoint = "/shipping/carriers/{$carrierId}/test";
        return $this->httpClient->post($endpoint);
    }

    /**
     * Obtém tarifas de uma transportadora
     */
    public function getCarrierRates(string $carrierId, array $shipmentData): array
    {
        $endpoint = "/shipping/carriers/{$carrierId}/rates";
        return $this->httpClient->post($endpoint, $shipmentData);
    }

    /**
     * Cria um pickup (coleta)
     */
    public function createPickup(array $pickupData): array
    {
        $endpoint = '/shipping/pickup';
        return $this->httpClient->post($endpoint, $pickupData);
    }

    /**
     * Cancela um pickup
     */
    public function cancelPickup(string $pickupId): bool
    {
        $endpoint = "/shipping/pickup/{$pickupId}/cancel";
        $response = $this->httpClient->post($endpoint);
        return $response['success'] ?? false;
    }

    /**
     * Obtém status de um pickup
     */
    public function getPickupStatus(string $pickupId): array
    {
        $endpoint = "/shipping/pickup/{$pickupId}/status";
        return $this->httpClient->get($endpoint);
    }

    /**
     * Obtém relatório de envios
     */
    public function getShippingReport(array $filters = []): array
    {
        $endpoint = '/shipping/reports';
        return $this->httpClient->get($endpoint, $filters);
    }

    /**
     * Obtém métricas de performance de envio
     */
    public function getShippingMetrics(array $filters = []): array
    {
        $endpoint = '/shipping/metrics';
        return $this->httpClient->get($endpoint, $filters);
    }

    /**
     * Obtém custos de envio por período
     */
    public function getShippingCosts(array $filters = []): array
    {
        $endpoint = '/shipping/costs';
        return $this->httpClient->get($endpoint, $filters);
    }

    /**
     * Configura regras de frete grátis
     */
    public function configureFreeShippingRules(array $rules): bool
    {
        $endpoint = '/shipping/free-shipping/rules';
        $response = $this->httpClient->post($endpoint, $rules);
        return $response['success'] ?? false;
    }

    /**
     * Obtém regras de frete grátis
     */
    public function getFreeShippingRules(): array
    {
        $endpoint = '/shipping/free-shipping/rules';
        return $this->httpClient->get($endpoint);
    }

    /**
     * Configura zonas de envio
     */
    public function configureShippingZones(array $zones): bool
    {
        $endpoint = '/shipping/zones';
        $response = $this->httpClient->post($endpoint, $zones);
        return $response['success'] ?? false;
    }

    /**
     * Obtém zonas de envio
     */
    public function getShippingZones(): array
    {
        $endpoint = '/shipping/zones';
        return $this->httpClient->get($endpoint);
    }

    /**
     * Obtém zona de envio para um endereço
     */
    public function getShippingZoneForAddress(array $address): array
    {
        $endpoint = '/shipping/zones/lookup';
        return $this->httpClient->post($endpoint, $address);
    }

    /**
     * Configura templates de notificação de envio
     */
    public function configureShippingNotifications(array $templates): bool
    {
        $endpoint = '/shipping/notifications/templates';
        $response = $this->httpClient->post($endpoint, $templates);
        return $response['success'] ?? false;
    }

    /**
     * Envia notificação de envio
     */
    public function sendShippingNotification(string $shipmentId, string $type): bool
    {
        $endpoint = "/shipping/shipments/{$shipmentId}/notify";
        $response = $this->httpClient->post($endpoint, ['type' => $type]);
        return $response['success'] ?? false;
    }

    /**
     * Verifica se o módulo está inicializado
     */
    public function isInitialized(): bool
    {
        return $this->httpClient !== null && $this->config !== null;
    }
}