<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Events;

interface EventDispatcherInterface
{
    /**
     * Adicionar listener para um evento
     */
    public function listen(string $eventName, callable $listener, int $priority = 0): void;

    /**
     * Remover listener de um evento
     */
    public function removeListener(string $eventName, callable $listener): void;

    /**
     * Remover todos os listeners de um evento
     */
    public function removeAllListeners(string $eventName): void;

    /**
     * Disparar evento
     */
    public function dispatch(EventInterface $event): EventInterface;

    /**
     * Disparar evento por nome com dados
     */
    public function emit(string $eventName, array $data = []): EventInterface;

    /**
     * Verificar se tem listeners para um evento
     */
    public function hasListeners(string $eventName): bool;

    /**
     * Obter listeners de um evento
     */
    public function getListeners(string $eventName): array;

    /**
     * Adicionar subscriber
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void;

    /**
     * Remover subscriber
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber): void;
}