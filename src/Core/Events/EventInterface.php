<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Events;

interface EventInterface
{
    /**
     * Obter nome do evento
     */
    public function getName(): string;

    /**
     * Obter dados do evento
     */
    public function getData(): array;

    /**
     * Obter timestamp do evento
     */
    public function getTimestamp(): int;

    /**
     * Verificar se evento foi cancelado
     */
    public function isCanceled(): bool;

    /**
     * Cancelar evento (impede propagação)
     */
    public function cancel(): void;

    /**
     * Obter contexto adicional do evento
     */
    public function getContext(): array;

    /**
     * Definir contexto adicional
     */
    public function setContext(array $context): void;
}
