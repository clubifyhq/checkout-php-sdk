<?php

declare(strict_types=1);

namespace Clubify\Checkout\Attributes;

use Attribute;

/**
 * Attribute para marcar propriedades que precisam de validação
 *
 * Demonstra recursos PHP 8.2+ com Attributes
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Validated
{
    public function __construct(
        public readonly string|array $rules,
        public readonly ?string $message = null,
        public readonly bool $required = true,
        public readonly mixed $default = null,
    ) {}

    /**
     * Obtém regras como array
     */
    public function getRulesArray(): array
    {
        return is_string($this->rules) ? explode('|', $this->rules) : $this->rules;
    }

    /**
     * Verifica se a propriedade é obrigatória
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Obtém valor padrão
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Obtém mensagem de erro personalizada
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}