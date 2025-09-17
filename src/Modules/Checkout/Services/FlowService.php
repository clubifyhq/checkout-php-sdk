<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Checkout\Services;

use ClubifyCheckout\Services\BaseService;
use ClubifyCheckout\Modules\Checkout\Contracts\SessionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de Flow de Checkout
 *
 * Gerencia navegação e validação de flows durante
 * o processo de checkout.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas navegação de flows
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseService
 * - I: Interface Segregation - Usa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class FlowService extends BaseService
{
    private const CACHE_TTL = 600; // 10 minutos
    private const STEP_TTL = 1800; // 30 minutos

    // Steps padrão de checkout
    private const DEFAULT_STEPS = [
        'product_selection',
        'customer_info',
        'shipping_info',
        'payment_info',
        'order_review',
        'order_confirmation'
    ];

    // Validações por step
    private const STEP_VALIDATIONS = [
        'product_selection' => ['products'],
        'customer_info' => ['customer.email', 'customer.name'],
        'shipping_info' => ['shipping.address', 'shipping.method'],
        'payment_info' => ['payment.method', 'payment.data'],
        'order_review' => ['order.items', 'order.totals'],
        'order_confirmation' => ['order.id']
    ];

    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        array $config = []
    ) {
        parent::__construct($logger, $cache, $config);
    }

    /**
     * Cria novo flow de checkout
     */
    public function create(string $organizationId, array $flowConfig): array
    {
        return $this->executeWithMetrics('flow_create', function () use ($organizationId, $flowConfig) {
            // Valida configuração do flow
            $validatedConfig = $this->validateFlowConfig($flowConfig);

            $flow = [
                'id' => uniqid('flow_'),
                'organization_id' => $organizationId,
                'name' => $flowConfig['name'] ?? 'Checkout Flow',
                'type' => $flowConfig['type'] ?? 'standard',
                'steps' => $validatedConfig['steps'],
                'config' => $validatedConfig['config'] ?? [],
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Cache do flow
            $this->setCacheItem("flow_{$flow['id']}", $flow, self::CACHE_TTL);

            $this->logger->info('Flow de checkout criado', [
                'flow_id' => $flow['id'],
                'organization_id' => $organizationId,
                'type' => $flow['type'],
                'steps_count' => count($flow['steps'])
            ]);

            return $flow;
        });
    }

    /**
     * Navega para próximo passo do flow
     */
    public function navigate(string $sessionId, string $currentStep, array $data = []): array
    {
        return $this->executeWithMetrics('flow_navigate', function () use ($sessionId, $currentStep, $data) {
            // Busca sessão
            $session = $this->sessionRepository->find($sessionId);
            if (!$session) {
                throw new \InvalidArgumentException('Sessão não encontrada');
            }

            // Obtém configuração do flow
            $flowConfig = $this->getFlowConfig($sessionId);
            if (!$flowConfig) {
                throw new \InvalidArgumentException('Flow não configurado para esta sessão');
            }

            // Valida step atual
            $this->validateCurrentStep($sessionId, $currentStep, $data);

            // Determina próximo step
            $nextStep = $this->getNextStep($flowConfig, $currentStep);

            // Atualiza sessão com dados do step
            $sessionData = [
                'current_step' => $nextStep,
                'step_data' => array_merge($session['step_data'] ?? [], [$currentStep => $data]),
                'completed_steps' => $this->getCompletedSteps($session, $currentStep)
            ];

            $session = $this->sessionRepository->update($sessionId, $sessionData);

            // Adiciona evento de navegação
            $this->sessionRepository->addEvent($sessionId, [
                'type' => 'flow_navigation',
                'data' => [
                    'from_step' => $currentStep,
                    'to_step' => $nextStep,
                    'flow_id' => $flowConfig['id'] ?? null
                ],
                'timestamp' => time()
            ]);

            $this->logger->info('Navegação de flow realizada', [
                'session_id' => $sessionId,
                'from_step' => $currentStep,
                'to_step' => $nextStep,
                'flow_id' => $flowConfig['id'] ?? null
            ]);

            return [
                'session_id' => $sessionId,
                'current_step' => $nextStep,
                'previous_step' => $currentStep,
                'step_config' => $this->getStepConfig($flowConfig, $nextStep),
                'progress' => $this->calculateProgress($flowConfig, $nextStep),
                'session' => $session
            ];
        });
    }

    /**
     * Obtém configuração do flow
     */
    public function getConfig(string $sessionId): ?array
    {
        return $this->getCachedOrExecute("flow_config_{$sessionId}", function () use ($sessionId) {
            $session = $this->sessionRepository->find($sessionId);
            if (!$session || empty($session['flow_id'])) {
                return $this->getDefaultFlowConfig();
            }

            return $this->getFlowById($session['flow_id']);
        }, self::CACHE_TTL);
    }

    /**
     * Valida dados do step
     */
    public function validate(string $sessionId, string $step, array $data): array
    {
        return $this->executeWithMetrics('flow_validate', function () use ($sessionId, $step, $data) {
            $errors = [];
            $warnings = [];

            // Obtém validações do step
            $validations = self::STEP_VALIDATIONS[$step] ?? [];

            foreach ($validations as $field) {
                $value = $this->getNestedValue($data, $field);

                if ($this->isRequired($field) && empty($value)) {
                    $errors[] = "Campo obrigatório '{$field}' não preenchido";
                }

                // Validações específicas por campo
                $fieldErrors = $this->validateField($field, $value);
                $errors = array_merge($errors, $fieldErrors);
            }

            // Validações customizadas por step
            $customValidations = $this->getCustomValidations($sessionId, $step, $data);
            $errors = array_merge($errors, $customValidations['errors'] ?? []);
            $warnings = array_merge($warnings, $customValidations['warnings'] ?? []);

            $isValid = empty($errors);

            $this->logger->info('Validação de step realizada', [
                'session_id' => $sessionId,
                'step' => $step,
                'is_valid' => $isValid,
                'errors_count' => count($errors),
                'warnings_count' => count($warnings)
            ]);

            return [
                'valid' => $isValid,
                'errors' => $errors,
                'warnings' => $warnings,
                'validated_fields' => array_keys($data)
            ];
        });
    }

    /**
     * Volta para step anterior
     */
    public function goBack(string $sessionId): array
    {
        return $this->executeWithMetrics('flow_go_back', function () use ($sessionId) {
            $session = $this->sessionRepository->find($sessionId);
            if (!$session) {
                throw new \InvalidArgumentException('Sessão não encontrada');
            }

            $flowConfig = $this->getFlowConfig($sessionId);
            $currentStep = $session['current_step'] ?? null;

            $previousStep = $this->getPreviousStep($flowConfig, $currentStep);

            if (!$previousStep) {
                throw new \InvalidArgumentException('Não há step anterior disponível');
            }

            // Atualiza sessão
            $session = $this->sessionRepository->update($sessionId, [
                'current_step' => $previousStep
            ]);

            $this->logger->info('Retorno de step realizado', [
                'session_id' => $sessionId,
                'from_step' => $currentStep,
                'to_step' => $previousStep
            ]);

            return [
                'session_id' => $sessionId,
                'current_step' => $previousStep,
                'previous_step' => $currentStep,
                'step_config' => $this->getStepConfig($flowConfig, $previousStep),
                'progress' => $this->calculateProgress($flowConfig, $previousStep),
                'session' => $session
            ];
        });
    }

    /**
     * Pula step (se permitido)
     */
    public function skipStep(string $sessionId, string $step, string $reason = ''): array
    {
        return $this->executeWithMetrics('flow_skip_step', function () use ($sessionId, $step, $reason) {
            $flowConfig = $this->getFlowConfig($sessionId);

            // Verifica se step pode ser pulado
            if (!$this->canSkipStep($flowConfig, $step)) {
                throw new \InvalidArgumentException("Step '{$step}' não pode ser pulado");
            }

            // Registra evento de skip
            $this->sessionRepository->addEvent($sessionId, [
                'type' => 'step_skipped',
                'data' => [
                    'step' => $step,
                    'reason' => $reason
                ],
                'timestamp' => time()
            ]);

            $this->logger->info('Step pulado', [
                'session_id' => $sessionId,
                'step' => $step,
                'reason' => $reason
            ]);

            // Navega para próximo step
            return $this->navigate($sessionId, $step, []);
        });
    }

    /**
     * Obtém progresso do flow
     */
    public function getProgress(string $sessionId): array
    {
        $session = $this->sessionRepository->find($sessionId);
        $flowConfig = $this->getFlowConfig($sessionId);

        if (!$session || !$flowConfig) {
            return ['percentage' => 0, 'current_step' => null, 'total_steps' => 0];
        }

        $currentStep = $session['current_step'] ?? null;
        $progress = $this->calculateProgress($flowConfig, $currentStep);

        return [
            'percentage' => $progress,
            'current_step' => $currentStep,
            'total_steps' => count($flowConfig['steps']),
            'completed_steps' => count($session['completed_steps'] ?? [])
        ];
    }

    /**
     * Obtém configuração padrão de flow
     */
    private function getDefaultFlowConfig(): array
    {
        return [
            'id' => 'default',
            'name' => 'Standard Checkout Flow',
            'type' => 'standard',
            'steps' => self::DEFAULT_STEPS,
            'config' => [
                'allow_skip' => ['shipping_info'],
                'conditional_steps' => [
                    'shipping_info' => 'requires_shipping'
                ]
            ]
        ];
    }

    /**
     * Valida configuração do flow
     */
    private function validateFlowConfig(array $config): array
    {
        if (empty($config['steps']) || !is_array($config['steps'])) {
            throw new \InvalidArgumentException('Steps do flow são obrigatórios');
        }

        // Valida steps obrigatórios
        $requiredSteps = ['customer_info', 'payment_info', 'order_confirmation'];
        foreach ($requiredSteps as $required) {
            if (!in_array($required, $config['steps'])) {
                throw new \InvalidArgumentException("Step obrigatório '{$required}' não encontrado");
            }
        }

        return $config;
    }

    /**
     * Valida step atual
     */
    private function validateCurrentStep(string $sessionId, string $step, array $data): void
    {
        $validation = $this->validate($sessionId, $step, $data);

        if (!$validation['valid']) {
            $errors = implode(', ', $validation['errors']);
            throw new \InvalidArgumentException("Dados inválidos para o step '{$step}': {$errors}");
        }
    }

    /**
     * Obtém próximo step
     */
    private function getNextStep(array $flowConfig, string $currentStep): ?string
    {
        $steps = $flowConfig['steps'];
        $currentIndex = array_search($currentStep, $steps);

        if ($currentIndex === false) {
            return $steps[0] ?? null; // Primeiro step se não encontrou atual
        }

        return $steps[$currentIndex + 1] ?? null; // Próximo step ou null se for o último
    }

    /**
     * Obtém step anterior
     */
    private function getPreviousStep(array $flowConfig, string $currentStep): ?string
    {
        $steps = $flowConfig['steps'];
        $currentIndex = array_search($currentStep, $steps);

        if ($currentIndex === false || $currentIndex === 0) {
            return null;
        }

        return $steps[$currentIndex - 1];
    }

    /**
     * Obtém configuração do step
     */
    private function getStepConfig(array $flowConfig, string $step): array
    {
        $config = $flowConfig['config'] ?? [];
        $stepConfig = $config['steps'][$step] ?? [];

        return array_merge([
            'name' => $step,
            'title' => ucwords(str_replace('_', ' ', $step)),
            'validations' => self::STEP_VALIDATIONS[$step] ?? [],
            'required' => true,
            'skippable' => $this->canSkipStep($flowConfig, $step)
        ], $stepConfig);
    }

    /**
     * Calcula progresso
     */
    private function calculateProgress(array $flowConfig, ?string $currentStep): float
    {
        if (!$currentStep) {
            return 0.0;
        }

        $steps = $flowConfig['steps'];
        $currentIndex = array_search($currentStep, $steps);

        if ($currentIndex === false) {
            return 0.0;
        }

        return round(($currentIndex / count($steps)) * 100, 2);
    }

    /**
     * Verifica se step pode ser pulado
     */
    private function canSkipStep(array $flowConfig, string $step): bool
    {
        $allowSkip = $flowConfig['config']['allow_skip'] ?? [];
        return in_array($step, $allowSkip);
    }

    /**
     * Obtém steps completados
     */
    private function getCompletedSteps(array $session, string $currentStep): array
    {
        $completed = $session['completed_steps'] ?? [];

        if (!in_array($currentStep, $completed)) {
            $completed[] = $currentStep;
        }

        return $completed;
    }

    /**
     * Obtém valor aninhado do array
     */
    private function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Verifica se campo é obrigatório
     */
    private function isRequired(string $field): bool
    {
        $optional = ['shipping.method', 'customer.phone'];
        return !in_array($field, $optional);
    }

    /**
     * Valida campo específico
     */
    private function validateField(string $field, mixed $value): array
    {
        $errors = [];

        switch ($field) {
            case 'customer.email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Email inválido';
                }
                break;

            case 'payment.method':
                $validMethods = ['credit_card', 'debit_card', 'pix', 'boleto'];
                if ($value && !in_array($value, $validMethods)) {
                    $errors[] = 'Método de pagamento inválido';
                }
                break;

            case 'shipping.address':
                if (is_array($value)) {
                    $required = ['street', 'city', 'state', 'zip_code'];
                    foreach ($required as $req) {
                        if (empty($value[$req])) {
                            $errors[] = "Campo de endereço '{$req}' obrigatório";
                        }
                    }
                }
                break;
        }

        return $errors;
    }

    /**
     * Obtém validações customizadas
     */
    private function getCustomValidations(string $sessionId, string $step, array $data): array
    {
        // Implementação de validações customizadas
        // Em produção seria extensível via plugins/hooks

        return ['errors' => [], 'warnings' => []];
    }

    /**
     * Obtém flow por ID
     */
    private function getFlowById(string $flowId): ?array
    {
        return $this->getCachedOrExecute("flow_{$flowId}", function () use ($flowId) {
            // Em produção seria consulta ao banco
            if ($flowId === 'default') {
                return $this->getDefaultFlowConfig();
            }
            return null;
        }, self::CACHE_TTL);
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return array_merge(parent::getMetrics(), [
            'default_steps_count' => count(self::DEFAULT_STEPS),
            'step_ttl' => self::STEP_TTL,
            'cache_ttl' => self::CACHE_TTL
        ]);
    }
}