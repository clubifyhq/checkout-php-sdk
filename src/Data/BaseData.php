<?php

declare(strict_types=1);

namespace Clubify\Checkout\Data;

use Clubify\Checkout\Contracts\ValidatableInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Carbon\Carbon;

/**
 * Classe base para DTOs (Data Transfer Objects)
 *
 * Fornece funcionalidades básicas para validação, serialização e manipulação de dados.
 * Utiliza recursos avançados do PHP 8.2+:
 * - Readonly Properties: Para imutabilidade de dados críticos
 * - Union Types: Para flexibilidade de tipos
 * - Attributes: Para metadados de validação
 * - Constructor Property Promotion: Para código mais limpo
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas dados e validação
 * - O: Open/Closed - Extensível sem modificação
 * - L: Liskov Substitution - Pode ser substituída por subclasses
 * - I: Interface Segregation - Implementa apenas interfaces necessárias
 * - D: Dependency Inversion - Depende de abstrações
 */
abstract class BaseData implements ValidatableInterface, \JsonSerializable, \ArrayAccess
{
    protected array $data = [];
    protected array $errors = [];
    protected readonly bool $immutable;
    private bool $validated = false;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->fillFromArray($data);
    }

    /**
     * Preenche o objeto a partir de um array
     */
    protected function fillFromArray(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Valida os dados do objeto
     */
    public function validate(): bool
    {
        $this->errors = [];
        $rules = $this->getRules();

        foreach ($rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;
            $this->validateField($field, $value, $fieldRules);
        }

        $this->validated = true;

        if (!empty($this->errors)) {
            throw new ValidationException('Validation failed', $this->errors);
        }

        return true;
    }

    /**
     * Valida um campo específico
     */
    protected function validateField(string $field, mixed $value, array $rules): void
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $this->applyRule($field, $value, $rule);
            } elseif (is_array($rule)) {
                $ruleName = $rule[0];
                $parameters = array_slice($rule, 1);
                $this->applyRule($field, $value, $ruleName, $parameters);
            }
        }
    }

    /**
     * Aplica uma regra de validação
     */
    protected function applyRule(string $field, mixed $value, string $rule, array $parameters = []): void
    {
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, "Field {$field} is required");
                }
                break;

            case 'string':
                if (!is_null($value) && !is_string($value)) {
                    $this->addError($field, "Field {$field} must be a string");
                }
                break;

            case 'integer':
                if (!is_null($value) && !is_int($value)) {
                    $this->addError($field, "Field {$field} must be an integer");
                }
                break;

            case 'numeric':
                if (!is_null($value) && !is_numeric($value)) {
                    $this->addError($field, "Field {$field} must be numeric");
                }
                break;

            case 'email':
                if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "Field {$field} must be a valid email");
                }
                break;

            case 'min':
                $min = $parameters[0] ?? 0;
                if (!is_null($value) && strlen((string)$value) < $min) {
                    $this->addError($field, "Field {$field} must be at least {$min} characters");
                }
                break;

            case 'max':
                $max = $parameters[0] ?? 255;
                if (!is_null($value) && strlen((string)$value) > $max) {
                    $this->addError($field, "Field {$field} must not exceed {$max} characters");
                }
                break;

            case 'date':
                if (!is_null($value) && !$this->isValidDate($value)) {
                    $this->addError($field, "Field {$field} must be a valid date");
                }
                break;

            case 'uuid':
                if (!is_null($value) && !$this->isValidUuid($value)) {
                    $this->addError($field, "Field {$field} must be a valid UUID");
                }
                break;

            case 'array':
                if (!is_null($value) && !is_array($value)) {
                    $this->addError($field, "Field {$field} must be an array");
                }
                break;

            case 'in':
                if (!is_null($value) && !in_array($value, $parameters)) {
                    $allowed = implode(', ', $parameters);
                    $this->addError($field, "Field {$field} must be one of: {$allowed}");
                }
                break;
        }
    }

    /**
     * Adiciona um erro de validação
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Verifica se uma data é válida
     */
    protected function isValidDate(mixed $value): bool
    {
        try {
            if (is_string($value)) {
                Carbon::parse($value);
                return true;
            } elseif ($value instanceof \DateTime) {
                return true;
            }
            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Verifica se um UUID é válido
     */
    protected function isValidUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * Verifica se os dados são válidos
     */
    public function isValid(): bool
    {
        if (!$this->validated) {
            try {
                $this->validate();
            } catch (ValidationException) {
                return false;
            }
        }

        return empty($this->errors);
    }

    /**
     * Obtém os erros de validação
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtém as mensagens de erro de validação
     */
    public function getMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return $messages;
    }

    /**
     * Converte o objeto para array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Converte o objeto para JSON
     */
    public function toJson(): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR);
    }

    /**
     * Implementação de JsonSerializable
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Implementação de ArrayAccess - offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Implementação de ArrayAccess - offsetGet
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * Implementação de ArrayAccess - offsetSet
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Implementação de ArrayAccess - offsetUnset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Magic method para acessar propriedades
     */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Magic method para definir propriedades
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Magic method para verificar se propriedade existe
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Magic method para remover propriedade
     */
    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    /**
     * Cria uma nova instância a partir de um array
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    /**
     * Cria uma nova instância a partir de JSON
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return new static($data);
    }

    /**
     * Obtém as regras de validação (deve ser implementado pelas subclasses)
     */
    abstract public function getRules(): array;
}