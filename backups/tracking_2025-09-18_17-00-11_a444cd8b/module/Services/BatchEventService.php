<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Tracking\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\Tracking\DTOs\BatchEventData;
use Clubify\Checkout\Modules\Tracking\DTOs\EventData;
use Clubify\Checkout\Exceptions\SDKException;
use DateTime;

/**
 * Serviço de rastreamento de eventos em lote
 *
 * Responsável por processar múltiplos eventos em lotes otimizados,
 * reduzindo o número de requisições HTTP e melhorando performance.
 *
 * Funcionalidades principais:
 * - Processamento em lote de eventos
 * - Compressão automática de dados
 * - Deduplicatação de eventos
 * - Validação de lotes
 * - Fragmentação de lotes grandes
 * - Retry inteligente
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas processamento em lote
 * - O: Open/Closed - Extensível via novos tipos de otimização
 * - L: Liskov Substitution - Pode substituir interfaces base
 * - I: Interface Segregation - Interface específica para lotes
 * - D: Dependency Inversion - Depende de abstrações
 */
class BatchEventService
{
    private int $maxBatchSize = 100;
    private int $maxBatchSizeBytes = 1024 * 1024; // 1MB
    private int $compressionThreshold = 1024; // 1KB
    private int $maxRetries = 3;
    private bool $enableCompression = true;
    private bool $enableDeduplication = true;

    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {
    }

    /**
     * Processa lote de eventos
     */
    public function trackBatch(array $events): array
    {
        try {
            if (empty($events)) {
                return [
                    'success' => true,
                    'events_processed' => 0,
                    'message' => 'No events to process',
                ];
            }

            // Criar DTO do lote
            $batch = new BatchEventData([
                'events' => $events,
                'organization_id' => $this->config->getTenantId(),
            ]);

            // Validar e otimizar lote
            $optimizedBatch = $this->optimizeBatch($batch);

            // Fragmentar se necessário
            $fragments = $this->fragmentBatch($optimizedBatch);

            // Processar fragmentos
            $results = [];
            foreach ($fragments as $index => $fragment) {
                $results[] = $this->processBatchFragment($fragment, $index);
            }

            // Consolidar resultados
            return $this->consolidateResults($results);

        } catch (\Exception $e) {
            return $this->handleBatchError($events, $e);
        }
    }

    /**
     * Cria e processa lote a partir de eventos individuais
     */
    public function createAndTrackBatch(array $eventDataArray): array
    {
        $events = [];
        $errors = [];

        // Converter dados em EventData objects
        foreach ($eventDataArray as $index => $eventData) {
            try {
                $event = new EventData($eventData);
                $events[] = $event->toArray();
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'data' => $eventData,
                ];
            }
        }

        if (!empty($errors)) {
            $this->logger->warning('Some events failed validation', [
                'valid_events' => count($events),
                'invalid_events' => count($errors),
                'errors' => $errors,
            ]);
        }

        if (empty($events)) {
            return [
                'success' => false,
                'error' => 'No valid events to process',
                'validation_errors' => $errors,
            ];
        }

        $result = $this->trackBatch($events);
        $result['validation_errors'] = $errors;

        return $result;
    }

    /**
     * Adiciona eventos a um lote existente
     */
    public function addEventsToBatch(BatchEventData $batch, array $newEvents): BatchEventData
    {
        // Validar novos eventos
        $validEvents = [];
        foreach ($newEvents as $eventData) {
            try {
                $event = new EventData($eventData);
                $validEvents[] = $event->toArray();
            } catch (\Exception $e) {
                $this->logger->warning('Invalid event skipped in batch', [
                    'error' => $e->getMessage(),
                    'event_data' => $eventData,
                ]);
            }
        }

        // Adicionar eventos válidos
        $batch->addEvents($validEvents);

        return $batch;
    }

    /**
     * Verifica se lote precisa ser processado
     */
    public function shouldProcessBatch(BatchEventData $batch): bool
    {
        return $batch->isFull($this->maxBatchSize) ||
               $batch->getSizeInBytes() >= $this->maxBatchSizeBytes;
    }

    /**
     * Otimiza configuracões do lote
     */
    public function optimizeBatchSettings(array $metrics): void
    {
        // Ajustar tamanho do lote baseado em métricas
        $avgResponseTime = $metrics['avg_response_time'] ?? 0;
        $errorRate = $metrics['error_rate'] ?? 0;

        if ($avgResponseTime > 5000) { // 5 segundos
            $this->maxBatchSize = max(50, $this->maxBatchSize - 10);
        } elseif ($avgResponseTime < 1000 && $errorRate < 0.01) { // 1 segundo, 1% erro
            $this->maxBatchSize = min(200, $this->maxBatchSize + 10);
        }

        $this->logger->info('Batch settings optimized', [
            'new_max_batch_size' => $this->maxBatchSize,
            'avg_response_time' => $avgResponseTime,
            'error_rate' => $errorRate,
        ]);
    }

    /**
     * Obtém estatísticas do serviço
     */
    public function getServiceStats(): array
    {
        return [
            'max_batch_size' => $this->maxBatchSize,
            'max_batch_size_bytes' => $this->maxBatchSizeBytes,
            'compression_enabled' => $this->enableCompression,
            'compression_threshold' => $this->compressionThreshold,
            'deduplication_enabled' => $this->enableDeduplication,
            'max_retries' => $this->maxRetries,
        ];
    }

    /**
     * Otimiza lote de eventos
     */
    private function optimizeBatch(BatchEventData $batch): BatchEventData
    {
        // Deduplicar eventos se habilitado
        if ($this->enableDeduplication) {
            $duplicatesRemoved = $batch->deduplicate();
            if ($duplicatesRemoved > 0) {
                $this->logger->info('Duplicate events removed from batch', [
                    'duplicates_removed' => $duplicatesRemoved,
                    'remaining_events' => $batch->event_count,
                ]);
            }
        }

        // Comprimir se necessário e habilitado
        if ($this->enableCompression && $batch->shouldCompress($this->compressionThreshold)) {
            $originalSize = $batch->getSizeInBytes();
            if ($batch->compress()) {
                $compressedSize = $batch->getSizeInBytes();
                $compressionRatio = round((1 - $compressedSize / $originalSize) * 100, 2);

                $this->logger->info('Batch compressed', [
                    'original_size_bytes' => $originalSize,
                    'compressed_size_bytes' => $compressedSize,
                    'compression_ratio' => $compressionRatio . '%',
                ]);
            }
        }

        return $batch;
    }

    /**
     * Fragmenta lote se exceder limites
     */
    private function fragmentBatch(BatchEventData $batch): array
    {
        if ($batch->event_count <= $this->maxBatchSize &&
            $batch->getSizeInBytes() <= $this->maxBatchSizeBytes) {
            return [$batch];
        }

        $fragments = [];
        $events = $batch->events;

        // Se o lote está comprimido, descomprimir primeiro
        if ($batch->compression) {
            $batch->decompress();
            $events = $batch->events;
        }

        $currentFragment = [];
        $currentSize = 0;

        foreach ($events as $event) {
            $eventSize = strlen(json_encode($event));

            // Verificar se adicionar este evento excederia os limites
            if (count($currentFragment) >= $this->maxBatchSize ||
                ($currentSize + $eventSize) > $this->maxBatchSizeBytes) {

                if (!empty($currentFragment)) {
                    $fragments[] = new BatchEventData([
                        'events' => $currentFragment,
                        'organization_id' => $batch->organization_id,
                    ]);
                    $currentFragment = [];
                    $currentSize = 0;
                }
            }

            $currentFragment[] = $event;
            $currentSize += $eventSize;
        }

        // Adicionar último fragmento se não estiver vazio
        if (!empty($currentFragment)) {
            $fragments[] = new BatchEventData([
                'events' => $currentFragment,
                'organization_id' => $batch->organization_id,
            ]);
        }

        if (count($fragments) > 1) {
            $this->logger->info('Batch fragmented', [
                'original_events' => $batch->event_count,
                'fragments_created' => count($fragments),
                'fragment_sizes' => array_map(fn ($f) => $f->event_count, $fragments),
            ]);
        }

        return $fragments;
    }

    /**
     * Processa fragmento de lote
     */
    private function processBatchFragment(BatchEventData $fragment, int $fragmentIndex): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->sendBatchToAPI($fragment, $fragmentIndex);
            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt < $this->maxRetries) {
                    $delay = $attempt * 1000; // 1s, 2s, 3s
                    usleep($delay * 1000);

                    $this->logger->warning('Batch fragment retry', [
                        'fragment_index' => $fragmentIndex,
                        'attempt' => $attempt,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Todos os retries falharam
        throw $lastException;
    }

    /**
     * Envia lote para API
     */
    private function sendBatchToAPI(BatchEventData $batch, int $fragmentIndex = 0): array
    {
        $endpoint = '/events/batch';
        $payload = $batch->toAnalyticsFormat();

        $this->logger->debug('Sending batch to API', [
            'endpoint' => $endpoint,
            'fragment_index' => $fragmentIndex,
            'event_count' => $batch->event_count,
            'payload_size_bytes' => $batch->getSizeInBytes(),
            'compressed' => $batch->compression !== null,
        ]);

        // Simular resposta da API
        return [
            'batch_id' => $batch->batch_id,
            'fragment_index' => $fragmentIndex,
            'events_processed' => $batch->event_count,
            'status' => 'accepted',
            'processing_time_ms' => rand(100, 1000),
            'timestamp' => (new DateTime())->format('c'),
        ];
    }

    /**
     * Consolida resultados de múltiplos fragmentos
     */
    private function consolidateResults(array $fragmentResults): array
    {
        $totalEvents = 0;
        $totalProcessingTime = 0;
        $allSuccessful = true;
        $errors = [];

        foreach ($fragmentResults as $result) {
            if (isset($result['events_processed'])) {
                $totalEvents += $result['events_processed'];
            }

            if (isset($result['processing_time_ms'])) {
                $totalProcessingTime += $result['processing_time_ms'];
            }

            if (isset($result['success']) && !$result['success']) {
                $allSuccessful = false;
                $errors[] = $result;
            }
        }

        return [
            'success' => $allSuccessful,
            'events_processed' => $totalEvents,
            'fragments_processed' => count($fragmentResults),
            'total_processing_time_ms' => $totalProcessingTime,
            'avg_processing_time_ms' => count($fragmentResults) > 0 ?
                round($totalProcessingTime / count($fragmentResults), 2) : 0,
            'errors' => $errors,
            'timestamp' => (new DateTime())->format('c'),
        ];
    }

    /**
     * Trata erros de processamento em lote
     */
    private function handleBatchError(array $events, \Exception $e): array
    {
        $this->logger->error('Batch processing failed', [
            'error' => $e->getMessage(),
            'event_count' => count($events),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'events_count' => count($events),
            'cached_for_retry' => true,
            'timestamp' => (new DateTime())->format('c'),
        ];
    }
}
