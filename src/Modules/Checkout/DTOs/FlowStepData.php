<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Checkout\DTOs;

use Clubify\Checkout\Core\DTOs\BaseData;

/**
 * DTO para step individual de flow de checkout
 *
 * Representa um step específico dentro de um flow:
 * - Configuração e estrutura do step
 * - Validações e regras de negócio
 * - Estado e progresso
 * - Dados coletados
 * - Métricas e analytics
 * - Condições de navegação
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas dados de step
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substitui BaseData
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class FlowStepData extends BaseData
{
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $type,
        public readonly int $order,
        public readonly bool $required = true,
        public readonly bool $completed = false,
        public readonly ?string $description = null,
        public readonly ?array $fields = null,
        public readonly ?array $validationRules = null,
        public readonly ?array $conditionalLogic = null,
        public readonly ?array $skipConditions = null,
        public readonly ?array $data = null,
        public readonly ?array $errors = null,
        public readonly ?array $warnings = null,
        public readonly ?array $uiConfig = null,
        public readonly ?array $analytics = null,
        public readonly ?string $nextStep = null,
        public readonly ?string $previousStep = null,
        public readonly ?array $alternativeSteps = null,
        public readonly ?float $completionTime = null,
        public readonly ?int $attemptCount = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $flowId = null,
        public readonly ?array $metadata = null,
        public readonly ?\DateTime $startedAt = null,
        public readonly ?\DateTime $completedAt = null
    ) {
        $this->validate();
    }

    /**
     * Regras de validação para step
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:100',
            'title' => 'required|string|min:1|max:255',
            'type' => 'required|string|in:form,review,confirmation,payment,shipping,customer_info,product_selection',
            'order' => 'required|integer|min:1',
        ];
    }

    /**
     * Converte para array completo
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'type' => $this->type,
            'order' => $this->order,
            'required' => $this->required,
            'completed' => $this->completed,
            'description' => $this->description,
            'fields' => $this->fields,
            'validation_rules' => $this->validationRules,
            'conditional_logic' => $this->conditionalLogic,
            'skip_conditions' => $this->skipConditions,
            'data' => $this->data,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'ui_config' => $this->uiConfig,
            'analytics' => $this->analytics,
            'next_step' => $this->nextStep,
            'previous_step' => $this->previousStep,
            'alternative_steps' => $this->alternativeSteps,
            'completion_time' => $this->completionTime,
            'attempt_count' => $this->attemptCount,
            'session_id' => $this->sessionId,
            'flow_id' => $this->flowId,
            'metadata' => $this->metadata,
            'started_at' => $this->startedAt?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Cria instância a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            title: $data['title'] ?? '',
            type: $data['type'] ?? 'form',
            order: (int)($data['order'] ?? 1),
            required: (bool)($data['required'] ?? true),
            completed: (bool)($data['completed'] ?? false),
            description: $data['description'] ?? null,
            fields: $data['fields'] ?? null,
            validationRules: $data['validation_rules'] ?? null,
            conditionalLogic: $data['conditional_logic'] ?? null,
            skipConditions: $data['skip_conditions'] ?? null,
            data: $data['data'] ?? null,
            errors: $data['errors'] ?? null,
            warnings: $data['warnings'] ?? null,
            uiConfig: $data['ui_config'] ?? null,
            analytics: $data['analytics'] ?? null,
            nextStep: $data['next_step'] ?? null,
            previousStep: $data['previous_step'] ?? null,
            alternativeSteps: $data['alternative_steps'] ?? null,
            completionTime: isset($data['completion_time']) ? (float)$data['completion_time'] : null,
            attemptCount: isset($data['attempt_count']) ? (int)$data['attempt_count'] : null,
            sessionId: $data['session_id'] ?? null,
            flowId: $data['flow_id'] ?? null,
            metadata: $data['metadata'] ?? null,
            startedAt: isset($data['started_at']) ? new \DateTime($data['started_at']) : null,
            completedAt: isset($data['completed_at']) ? new \DateTime($data['completed_at']) : null
        );
    }

    /**
     * Verifica se o step está completo
     */
    public function isCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * Verifica se o step é obrigatório
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Verifica se pode ser pulado
     */
    public function canBeSkipped(): bool
    {
        if ($this->required) {
            return false;
        }

        if ($this->skipConditions === null) {
            return true;
        }

        // Avalia condições de skip (implementação simplificada)
        return $this->evaluateSkipConditions();
    }

    /**
     * Verifica se tem erros
     */
    public function hasErrors(): bool
    {
        return $this->errors !== null && !empty($this->errors);
    }

    /**
     * Verifica se tem warnings
     */
    public function hasWarnings(): bool
    {
        return $this->warnings !== null && !empty($this->warnings);
    }

    /**
     * Verifica se tem dados coletados
     */
    public function hasData(): bool
    {
        return $this->data !== null && !empty($this->data);
    }

    /**
     * Verifica se step está em progresso
     */
    public function isInProgress(): bool
    {
        return $this->startedAt !== null && !$this->completed;
    }

    /**
     * Verifica se step foi iniciado
     */
    public function isStarted(): bool
    {
        return $this->startedAt !== null;
    }

    /**
     * Obtém tempo gasto no step
     */
    public function getTimeSpent(): ?float
    {
        if ($this->startedAt === null) {
            return null;
        }

        $endTime = $this->completedAt ?? new \DateTime();
        return $endTime->getTimestamp() - $this->startedAt->getTimestamp();
    }

    /**
     * Obtém progresso do step (0-100)
     */
    public function getProgress(): float
    {
        if ($this->completed) {
            return 100.0;
        }

        if (!$this->hasData() || $this->fields === null) {
            return 0.0;
        }

        $totalFields = count($this->fields);
        $filledFields = 0;

        foreach ($this->fields as $field) {
            $fieldName = $field['name'] ?? '';
            if (!empty($this->data[$fieldName])) {
                $filledFields++;
            }
        }

        return $totalFields > 0 ? round(($filledFields / $totalFields) * 100, 2) : 0.0;
    }

    /**
     * Obtém campos obrigatórios
     */
    public function getRequiredFields(): array
    {
        if ($this->fields === null) {
            return [];
        }

        return array_filter($this->fields, function ($field) {
            return $field['required'] ?? false;
        });
    }

    /**
     * Obtém campos opcionais
     */
    public function getOptionalFields(): array
    {
        if ($this->fields === null) {
            return [];
        }

        return array_filter($this->fields, function ($field) {
            return !($field['required'] ?? false);
        });
    }

    /**
     * Verifica se todos os campos obrigatórios estão preenchidos
     */
    public function hasAllRequiredFields(): bool
    {
        $requiredFields = $this->getRequiredFields();

        foreach ($requiredFields as $field) {
            $fieldName = $field['name'] ?? '';
            if (empty($this->data[$fieldName])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtém campos faltantes
     */
    public function getMissingFields(): array
    {
        $missing = [];
        $requiredFields = $this->getRequiredFields();

        foreach ($requiredFields as $field) {
            $fieldName = $field['name'] ?? '';
            if (empty($this->data[$fieldName])) {
                $missing[] = $fieldName;
            }
        }

        return $missing;
    }

    /**
     * Obtém configuração de UI
     */
    public function getUiConfig(): array
    {
        return $this->uiConfig ?? [
            'layout' => 'default',
            'show_progress' => true,
            'show_navigation' => true,
            'animation' => 'fade',
            'validation_mode' => 'onBlur'
        ];
    }

    /**
     * Obtém métricas de analytics
     */
    public function getAnalyticsData(): array
    {
        $baseAnalytics = [
            'step_name' => $this->name,
            'step_type' => $this->type,
            'step_order' => $this->order,
            'is_completed' => $this->completed,
            'has_errors' => $this->hasErrors(),
            'completion_time' => $this->completionTime,
            'attempt_count' => $this->attemptCount ?? 1,
            'progress_percentage' => $this->getProgress(),
            'time_spent' => $this->getTimeSpent()
        ];

        return array_merge($baseAnalytics, $this->analytics ?? []);
    }

    /**
     * Valida dados do step
     */
    public function validateData(): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        // Valida campos obrigatórios
        $missingFields = $this->getMissingFields();
        if (!empty($missingFields)) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Missing required fields: ' . implode(', ', $missingFields);
        }

        // Aplica regras de validação customizadas
        if ($this->validationRules !== null) {
            $customValidation = $this->applyValidationRules();
            $validation['errors'] = array_merge($validation['errors'], $customValidation['errors']);
            $validation['warnings'] = array_merge($validation['warnings'], $customValidation['warnings']);

            if (!empty($customValidation['errors'])) {
                $validation['valid'] = false;
            }
        }

        return $validation;
    }

    /**
     * Aplica lógica condicional
     */
    public function evaluateConditionalLogic(): array
    {
        if ($this->conditionalLogic === null) {
            return ['show' => true, 'next_step' => $this->nextStep];
        }

        // Implementação simplificada de lógica condicional
        $result = [
            'show' => true,
            'next_step' => $this->nextStep,
            'modifications' => []
        ];

        foreach ($this->conditionalLogic as $condition) {
            if ($this->evaluateCondition($condition)) {
                if (isset($condition['action']['hide'])) {
                    $result['show'] = false;
                }
                if (isset($condition['action']['redirect_to'])) {
                    $result['next_step'] = $condition['action']['redirect_to'];
                }
                if (isset($condition['action']['modify'])) {
                    $result['modifications'][] = $condition['action']['modify'];
                }
            }
        }

        return $result;
    }

    /**
     * Marca step como iniciado
     */
    public function markAsStarted(): self
    {
        $data = $this->toArray();
        $data['started_at'] = (new \DateTime())->format('Y-m-d H:i:s');
        $data['attempt_count'] = ($this->attemptCount ?? 0) + 1;

        return self::fromArray($data);
    }

    /**
     * Marca step como completo
     */
    public function markAsCompleted(float $completionTime = null): self
    {
        $data = $this->toArray();
        $data['completed'] = true;
        $data['completed_at'] = (new \DateTime())->format('Y-m-d H:i:s');

        if ($completionTime !== null) {
            $data['completion_time'] = $completionTime;
        } elseif ($this->startedAt !== null) {
            $data['completion_time'] = time() - $this->startedAt->getTimestamp();
        }

        return self::fromArray($data);
    }

    /**
     * Adiciona dados ao step
     */
    public function withData(array $newData): self
    {
        $data = $this->toArray();
        $data['data'] = array_merge($this->data ?? [], $newData);

        return self::fromArray($data);
    }

    /**
     * Adiciona erros ao step
     */
    public function withErrors(array $errors): self
    {
        $data = $this->toArray();
        $data['errors'] = array_merge($this->errors ?? [], $errors);

        return self::fromArray($data);
    }

    /**
     * Adiciona warnings ao step
     */
    public function withWarnings(array $warnings): self
    {
        $data = $this->toArray();
        $data['warnings'] = array_merge($this->warnings ?? [], $warnings);

        return self::fromArray($data);
    }

    /**
     * Limpa erros do step
     */
    public function clearErrors(): self
    {
        $data = $this->toArray();
        $data['errors'] = null;

        return self::fromArray($data);
    }

    /**
     * Avalia condições de skip
     */
    private function evaluateSkipConditions(): bool
    {
        if ($this->skipConditions === null) {
            return true;
        }

        // Implementação simplificada
        foreach ($this->skipConditions as $condition) {
            if (!$this->evaluateCondition($condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Aplica regras de validação
     */
    private function applyValidationRules(): array
    {
        $result = ['errors' => [], 'warnings' => []];

        if ($this->validationRules === null || $this->data === null) {
            return $result;
        }

        foreach ($this->validationRules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            $fieldValidation = $this->validateField($field, $value, $rules);

            $result['errors'] = array_merge($result['errors'], $fieldValidation['errors']);
            $result['warnings'] = array_merge($result['warnings'], $fieldValidation['warnings']);
        }

        return $result;
    }

    /**
     * Valida campo específico
     */
    private function validateField(string $field, $value, array $rules): array
    {
        $result = ['errors' => [], 'warnings' => []];

        foreach ($rules as $rule) {
            $ruleName = $rule['type'] ?? '';
            $ruleParams = $rule['params'] ?? [];

            switch ($ruleName) {
                case 'required':
                    if (empty($value)) {
                        $result['errors'][] = "Field '{$field}' is required";
                    }
                    break;

                case 'email':
                    if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $result['errors'][] = "Field '{$field}' must be a valid email";
                    }
                    break;

                case 'min_length':
                    $minLength = $ruleParams['length'] ?? 0;
                    if (strlen((string)$value) < $minLength) {
                        $result['errors'][] = "Field '{$field}' must be at least {$minLength} characters";
                    }
                    break;

                case 'max_length':
                    $maxLength = $ruleParams['length'] ?? 255;
                    if (strlen((string)$value) > $maxLength) {
                        $result['errors'][] = "Field '{$field}' must be no more than {$maxLength} characters";
                    }
                    break;

                case 'numeric':
                    if ($value && !is_numeric($value)) {
                        $result['errors'][] = "Field '{$field}' must be numeric";
                    }
                    break;
            }
        }

        return $result;
    }

    /**
     * Avalia condição específica
     */
    private function evaluateCondition(array $condition): bool
    {
        // Implementação simplificada de avaliação de condições
        $operator = $condition['operator'] ?? 'equals';
        $field = $condition['field'] ?? '';
        $expectedValue = $condition['value'] ?? null;
        $actualValue = $this->data[$field] ?? null;

        return match ($operator) {
            'equals' => $actualValue == $expectedValue,
            'not_equals' => $actualValue != $expectedValue,
            'contains' => str_contains((string)$actualValue, (string)$expectedValue),
            'greater_than' => (float)$actualValue > (float)$expectedValue,
            'less_than' => (float)$actualValue < (float)$expectedValue,
            'exists' => $actualValue !== null,
            'not_exists' => $actualValue === null,
            default => true
        };
    }
}
