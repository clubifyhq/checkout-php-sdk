<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Tracking\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\Tracking\DTOs\EventData;
use DateTime;

/**
 * Serviço de eventos beacon
 *
 * Especializado em eventos críticos que precisam ser enviados
 * mesmo quando o usuário sai da página (page unload, navegador fechando).
 */
class BeaconService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {}

    /**
     * Rastreia evento beacon
     */
    public function trackBeacon(array $eventData): array
    {
        try {
            $event = new EventData(array_merge($eventData, [
                'event_type' => $eventData['event_type'] ?? 'beacon_event',
                'timestamp' => new DateTime(),
                'session_id' => $this->generateSessionId(),
                'organization_id' => $this->config->getTenantId(),
            ]));
            
            // Eventos beacon têm prioridade alta
            $response = $this->sendBeaconToAPI($event);
            
            $this->logger->info('Beacon event tracked', [
                'event_type' => $event->event_type,
                'session_id' => $event->session_id,
            ]);
            
            return [
                'success' => true,
                'event_id' => $response['event_id'] ?? uniqid('beacon_'),
                'timestamp' => $event->getTimestampIso(),
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Beacon tracking failed', [
                'error' => $e->getMessage(),
                'event_data' => $eventData,
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => (new DateTime())->format('c'),
            ];
        }
    }

    /**
     * Rastreia saída de página
     */
    public function trackPageExit(array $pageData): array
    {
        return $this->trackBeacon(array_merge($pageData, [
            'event_type' => 'page_exit',
            'metadata' => array_merge($pageData['metadata'] ?? [], [
                'is_beacon' => true,
                'exit_type' => 'beacon',
            ]),
        ]));
    }

    /**
     * Rastreia fim de sessão
     */
    public function trackSessionEnd(array $sessionData): array
    {
        return $this->trackBeacon(array_merge($sessionData, [
            'event_type' => 'session_end',
            'metadata' => array_merge($sessionData['metadata'] ?? [], [
                'is_beacon' => true,
                'end_type' => 'beacon',
            ]),
        ]));
    }

    private function sendBeaconToAPI(EventData $event): array
    {
        // Simular envio prioritário para API
        $this->logger->debug('Sending beacon to API', [
            'event_type' => $event->event_type,
            'priority' => 'high',
        ]);
        
        return [
            'event_id' => uniqid('beacon_'),
            'status' => 'accepted_priority',
            'timestamp' => (new DateTime())->format('c'),
        ];
    }

    private function generateSessionId(): string
    {
        return session_id() ?: uniqid('session_', true);
    }
}
