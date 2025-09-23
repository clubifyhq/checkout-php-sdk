<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\Services;

use Clubify\Checkout\Modules\Cart\Contracts\CartRepositoryInterface;
use Clubify\Checkout\Modules\Cart\DTOs\NavigationData;
use Clubify\Checkout\Core\Config\Configuration;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de Navegação de Fluxos
 *
 * Sistema avançado de navegação de fluxos de ofertas e checkout,
 * permitindo experiências personalizadas e multi-step.
 *
 * Funcionalidades:
 * - Navegação de fluxos de ofertas
 * - Controle de steps/etapas
 * - Persistência de estado
 * - Analytics de fluxo
 * - A/B testing de fluxos
 *
 * Endpoints utilizados:
 * - GET/POST /navigation/flow/:offerId
 * - POST /navigation/flow/navigation/:id/continue
 * - GET /navigation/flow/navigation/:id
 * - POST /navigation/flow/navigation/:id/complete
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas navegação de fluxos
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substituível
 * - I: Interface Segregation - Interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class NavigationService
{
    private const CACHE_TTL = 900; // 15 minutos
    private const MAX_STEPS = 10;
    private const SESSION_TIMEOUT = 3600; // 1 hora

    public function __construct(
        private CartRepositoryInterface $repository,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
        private Configuration $config
    ) {
    }

    // ===========================================
    // OPERAÇÕES PRINCIPAIS DE NAVEGAÇÃO
    // ===========================================

    /**
     * Inicia navegação de fluxo
     */
    public function startFlow(string $offerId, array $context = []): array
    {
        $this->logger->info('Starting flow navigation', [
            'offer_id' => $offerId,
            'context' => $context
        ]);

        // Valida contexto inicial
        $this->validateFlowContext($context);

        // Inicia navegação via API
        $navigation = $this->repository->startFlowNavigation($offerId, $context);

        // Cria dados de navegação estruturados
        $navigationData = new NavigationData($navigation);

        // Cache da navegação
        $this->cacheNavigation($navigationData->navigation_id, $navigationData->toArray());

        $this->logger->info('Flow navigation started successfully', [
            'offer_id' => $offerId,
            'navigation_id' => $navigationData->navigation_id,
            'current_step' => $navigationData->current_step
        ]);

        return $navigationData->toArray();
    }

    /**
     * Continua navegação de fluxo
     */
    public function continueFlow(string $navigationId, array $stepData): array
    {
        $this->logger->info('Continuing flow navigation', [
            'navigation_id' => $navigationId,
            'step_data' => $stepData
        ]);

        // Busca navegação atual
        $currentNavigation = $this->getNavigation($navigationId);
        if (!$currentNavigation) {
            throw new \InvalidArgumentException('Navegação não encontrada');
        }

        // Valida dados do step
        $this->validateStepData($currentNavigation, $stepData);

        // Continua navegação via API
        $navigation = $this->repository->continueFlowNavigation($navigationId, $stepData);

        // Atualiza dados de navegação
        $navigationData = new NavigationData($navigation);

        // Atualiza cache
        $this->cacheNavigation($navigationId, $navigationData->toArray());

        $this->logger->info('Flow navigation continued successfully', [
            'navigation_id' => $navigationId,
            'previous_step' => $currentNavigation['current_step'] ?? null,
            'current_step' => $navigationData->current_step,
            'is_complete' => $navigationData->is_complete
        ]);

        return $navigationData->toArray();
    }

    /**
     * Obtém dados de navegação
     */
    public function getNavigation(string $navigationId): ?array
    {
        $this->logger->debug('Fetching navigation data', [
            'navigation_id' => $navigationId
        ]);

        // Verifica cache primeiro
        $cacheKey = "navigation_{$navigationId}";
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Busca na API
        $navigation = $this->repository->getFlowNavigation($navigationId);

        if ($navigation) {
            // Cache do resultado
            $this->cacheNavigation($navigationId, $navigation);
        }

        return $navigation;
    }

    /**
     * Finaliza navegação de fluxo
     */
    public function completeFlow(string $navigationId): array
    {
        $this->logger->info('Completing flow navigation', [
            'navigation_id' => $navigationId
        ]);

        // Busca navegação atual
        $currentNavigation = $this->getNavigation($navigationId);
        if (!$currentNavigation) {
            throw new \InvalidArgumentException('Navegação não encontrada');
        }

        // Verifica se pode completar
        $this->validateFlowCompletion($currentNavigation);

        // Completa navegação via API
        $result = $this->repository->completeFlowNavigation($navigationId);

        // Remove do cache (navegação finalizada)
        $this->clearNavigationCache($navigationId);

        $this->logger->info('Flow navigation completed successfully', [
            'navigation_id' => $navigationId,
            'total_steps' => $currentNavigation['total_steps'] ?? null,
            'conversion_result' => $result['conversion_result'] ?? null
        ]);

        return $result;
    }

    // ===========================================
    // OPERAÇÕES DE ANÁLISE E CONTROLE
    // ===========================================

    /**
     * Obtém próximo step do fluxo
     */
    public function getNextStep(string $navigationId): ?array
    {
        $navigation = $this->getNavigation($navigationId);
        if (!$navigation) {
            return null;
        }

        $navigationData = new NavigationData($navigation);

        if ($navigationData->is_complete) {
            return null;
        }

        return $navigationData->getNextStepData();
    }

    /**
     * Obtém step atual do fluxo
     */
    public function getCurrentStep(string $navigationId): ?array
    {
        $navigation = $this->getNavigation($navigationId);
        if (!$navigation) {
            return null;
        }

        $navigationData = new NavigationData($navigation);
        return $navigationData->getCurrentStepData();
    }

    /**
     * Verifica se navegação pode avançar
     */
    public function canProceed(string $navigationId): bool
    {
        $navigation = $this->getNavigation($navigationId);
        if (!$navigation) {
            return false;
        }

        $navigationData = new NavigationData($navigation);

        // Verifica se não está completa
        if ($navigationData->is_complete) {
            return false;
        }

        // Verifica se não expirou
        if ($this->isNavigationExpired($navigationData)) {
            return false;
        }

        // Verifica se step atual está válido
        return $navigationData->canProceedToNext();
    }

    /**
     * Obtém progresso da navegação
     */
    public function getProgress(string $navigationId): array
    {
        $navigation = $this->getNavigation($navigationId);
        if (!$navigation) {
            throw new \InvalidArgumentException('Navegação não encontrada');
        }

        $navigationData = new NavigationData($navigation);

        return [
            'navigation_id' => $navigationId,
            'current_step' => $navigationData->current_step,
            'total_steps' => $navigationData->total_steps,
            'progress_percentage' => $navigationData->getProgressPercentage(),
            'completed_steps' => $navigationData->getCompletedSteps(),
            'remaining_steps' => $navigationData->getRemainingSteps(),
            'is_complete' => $navigationData->is_complete,
            'can_proceed' => $this->canProceed($navigationId),
            'started_at' => $navigationData->started_at,
            'last_activity' => $navigationData->last_activity_at
        ];
    }

    // ===========================================
    // OPERAÇÕES DE HISTÓRICO E ANALYTICS
    // ===========================================

    /**
     * Obtém histórico de steps da navegação
     */
    public function getStepHistory(string $navigationId): array
    {
        $navigation = $this->getNavigation($navigationId);
        if (!$navigation) {
            return [];
        }

        return $navigation['step_history'] ?? [];
    }

    /**
     * Obtém estatísticas de tempo por step
     */
    public function getStepTimings(string $navigationId): array
    {
        $navigation = $this->getNavigation($navigationId);
        if (!$navigation) {
            return [];
        }

        $navigationData = new NavigationData($navigation);
        return $navigationData->getStepTimings();
    }

    /**
     * Obtém dados de conversão
     */
    public function getConversionData(string $navigationId): array
    {
        $navigation = $this->getNavigation($navigationId);
        if (!$navigation) {
            return [];
        }

        return [
            'navigation_id' => $navigationId,
            'offer_id' => $navigation['offer_id'] ?? null,
            'started_at' => $navigation['started_at'] ?? null,
            'completed_at' => $navigation['completed_at'] ?? null,
            'total_time' => $navigation['total_time'] ?? null,
            'steps_completed' => count($navigation['step_history'] ?? []),
            'conversion_rate' => $navigation['conversion_rate'] ?? null,
            'revenue' => $navigation['revenue'] ?? null,
            'abandoned_at_step' => $navigation['abandoned_at_step'] ?? null
        ];
    }

    // ===========================================
    // OPERAÇÕES DE ADMINISTRAÇÃO
    // ===========================================

    /**
     * Abandona navegação
     */
    public function abandonFlow(string $navigationId, string $reason = null): array
    {
        $this->logger->info('Abandoning flow navigation', [
            'navigation_id' => $navigationId,
            'reason' => $reason
        ]);

        $navigation = $this->getNavigation($navigationId);
        if (!$navigation) {
            throw new \InvalidArgumentException('Navegação não encontrada');
        }

        // Marca como abandonada via atualização direta
        $updatedNavigation = array_merge($navigation, [
            'status' => 'abandoned',
            'abandoned_at' => date('Y-m-d H:i:s'),
            'abandon_reason' => $reason,
            'is_complete' => false
        ]);

        // Atualiza cache
        $this->cacheNavigation($navigationId, $updatedNavigation);

        $this->logger->info('Flow navigation abandoned', [
            'navigation_id' => $navigationId,
            'reason' => $reason,
            'current_step' => $navigation['current_step'] ?? null
        ]);

        return $updatedNavigation;
    }

    /**
     * Redefine navegação para step específico
     */
    public function resetToStep(string $navigationId, int $stepNumber): array
    {
        $this->logger->info('Resetting navigation to step', [
            'navigation_id' => $navigationId,
            'target_step' => $stepNumber
        ]);

        $navigation = $this->getNavigation($navigationId);
        if (!$navigation) {
            throw new \InvalidArgumentException('Navegação não encontrada');
        }

        $navigationData = new NavigationData($navigation);

        if ($stepNumber < 1 || $stepNumber > $navigationData->total_steps) {
            throw new \InvalidArgumentException('Step inválido');
        }

        // Atualiza para o step desejado
        $updatedNavigation = $navigationData->resetToStep($stepNumber);

        // Atualiza cache
        $this->cacheNavigation($navigationId, $updatedNavigation);

        $this->logger->info('Navigation reset to step', [
            'navigation_id' => $navigationId,
            'new_current_step' => $stepNumber
        ]);

        return $updatedNavigation;
    }

    // ===========================================
    // MÉTODOS PRIVADOS DE VALIDAÇÃO
    // ===========================================

    /**
     * Valida contexto inicial do fluxo
     */
    private function validateFlowContext(array $context): void
    {
        // Validações básicas de contexto
        if (empty($context['session_id'])) {
            throw new \InvalidArgumentException('Session ID é obrigatório');
        }

        // Validações adicionais podem ser implementadas
        $this->logger->debug('Flow context validated', $context);
    }

    /**
     * Valida dados do step
     */
    private function validateStepData(array $navigation, array $stepData): void
    {
        $navigationData = new NavigationData($navigation);

        // Verifica se navegação pode prosseguir
        if ($navigationData->is_complete) {
            throw new \InvalidArgumentException('Navegação já está completa');
        }

        // Verifica se não expirou
        if ($this->isNavigationExpired($navigationData)) {
            throw new \InvalidArgumentException('Navegação expirou');
        }

        // Verifica limite de steps
        if ($navigationData->current_step >= self::MAX_STEPS) {
            throw new \InvalidArgumentException('Limite máximo de steps atingido');
        }

        $this->logger->debug('Step data validated', [
            'current_step' => $navigationData->current_step,
            'step_data' => $stepData
        ]);
    }

    /**
     * Valida se pode completar fluxo
     */
    private function validateFlowCompletion(array $navigation): void
    {
        $navigationData = new NavigationData($navigation);

        if ($navigationData->is_complete) {
            throw new \InvalidArgumentException('Navegação já está completa');
        }

        if (!$navigationData->canComplete()) {
            throw new \InvalidArgumentException('Navegação não pode ser completada no estado atual');
        }

        $this->logger->debug('Flow completion validated', [
            'navigation_id' => $navigationData->navigation_id
        ]);
    }

    /**
     * Verifica se navegação expirou
     */
    private function isNavigationExpired(NavigationData $navigationData): bool
    {
        if (!$navigationData->started_at) {
            return false;
        }

        $startTime = strtotime($navigationData->started_at);
        $currentTime = time();

        return ($currentTime - $startTime) > self::SESSION_TIMEOUT;
    }

    // ===========================================
    // MÉTODOS UTILITÁRIOS DE CACHE
    // ===========================================

    /**
     * Cache da navegação
     */
    private function cacheNavigation(string $navigationId, array $navigation): void
    {
        $cacheKey = "navigation_{$navigationId}";

        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($navigation);
        $cacheItem->expiresAfter(self::CACHE_TTL);
        $this->cache->save($cacheItem);
    }

    /**
     * Limpa cache da navegação
     */
    private function clearNavigationCache(string $navigationId): void
    {
        $cacheKey = "navigation_{$navigationId}";

        if ($this->cache->hasItem($cacheKey)) {
            $this->cache->deleteItem($cacheKey);
        }
    }

    /**
     * Obtém estatísticas do serviço
     */
    public function getMetrics(): array
    {
        return [
            'service' => 'NavigationService',
            'max_steps' => self::MAX_STEPS,
            'session_timeout' => self::SESSION_TIMEOUT,
            'cache_ttl' => self::CACHE_TTL
        ];
    }
}