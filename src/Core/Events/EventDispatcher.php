<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Events;

/**
 * Dispatcher de eventos do Clubify SDK
 *
 * Sistema de eventos que permite adicionar listeners, subscribers
 * e disparar eventos de forma organizada e performática.
 */
class EventDispatcher implements EventDispatcherInterface
{
    private array $listeners = [];
    private array $sorted = [];

    public function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];

        // Marcar como não ordenado para reordenar na próxima execução
        unset($this->sorted[$eventName]);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $index => $listenerData) {
            if ($listenerData['listener'] === $listener) {
                unset($this->listeners[$eventName][$index]);
                unset($this->sorted[$eventName]);
                break;
            }
        }
    }

    public function removeAllListeners(string $eventName): void
    {
        unset($this->listeners[$eventName], $this->sorted[$eventName]);
    }

    public function dispatch(EventInterface $event): EventInterface
    {
        $eventName = $event->getName();
        $listeners = $this->getListeners($eventName);

        foreach ($listeners as $listener) {
            // Se evento foi cancelado, parar propagação
            if ($event->isCanceled()) {
                break;
            }

            try {
                $listener($event);
            } catch (\Throwable $e) {
                // Log error mas não interrompe outros listeners
                error_log("Event listener error for {$eventName}: " . $e->getMessage());
            }
        }

        return $event;
    }

    public function emit(string $eventName, array $data = []): EventInterface
    {
        $event = new Event($eventName, $data);
        return $this->dispatch($event);
    }

    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }

    public function getListeners(string $eventName): array
    {
        if (!isset($this->listeners[$eventName])) {
            return [];
        }

        // Se já está ordenado, retornar cache
        if (isset($this->sorted[$eventName])) {
            return $this->sorted[$eventName];
        }

        // Ordenar listeners por prioridade (maior primeiro)
        $listeners = $this->listeners[$eventName];

        usort($listeners, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        // Extrair apenas os callables
        $this->sorted[$eventName] = array_column($listeners, 'listener');

        return $this->sorted[$eventName];
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $events = $subscriber::getSubscribedEvents();

        foreach ($events as $eventName => $params) {
            if (is_string($params)) {
                // Formato: 'event.name' => 'methodName'
                $this->listen($eventName, [$subscriber, $params]);
            } elseif (is_array($params)) {
                if (is_string($params[0])) {
                    // Formato: 'event.name' => ['methodName', priority]
                    $method = $params[0];
                    $priority = $params[1] ?? 0;
                    $this->listen($eventName, [$subscriber, $method], $priority);
                } else {
                    // Formato: 'event.name' => [['method1', priority1], ['method2', priority2]]
                    foreach ($params as $listener) {
                        $method = $listener[0];
                        $priority = $listener[1] ?? 0;
                        $this->listen($eventName, [$subscriber, $method], $priority);
                    }
                }
            }
        }
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $events = $subscriber::getSubscribedEvents();

        foreach ($events as $eventName => $params) {
            if (is_string($params)) {
                $this->removeListener($eventName, [$subscriber, $params]);
            } elseif (is_array($params)) {
                if (is_string($params[0])) {
                    $this->removeListener($eventName, [$subscriber, $params[0]]);
                } else {
                    foreach ($params as $listener) {
                        $this->removeListener($eventName, [$subscriber, $listener[0]]);
                    }
                }
            }
        }
    }

    /**
     * Obter todas as estatísticas dos listeners
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_events' => count($this->listeners),
            'total_listeners' => 0,
            'events' => [],
        ];

        foreach ($this->listeners as $eventName => $listeners) {
            $listenerCount = count($listeners);
            $stats['total_listeners'] += $listenerCount;
            $stats['events'][$eventName] = $listenerCount;
        }

        return $stats;
    }

    /**
     * Limpar todos os listeners
     */
    public function clear(): void
    {
        $this->listeners = [];
        $this->sorted = [];
    }

    /**
     * Verificar se dispatcher está vazio
     */
    public function isEmpty(): bool
    {
        return empty($this->listeners);
    }

    /**
     * Dispatch com timeout (para listeners lentos)
     */
    public function dispatchWithTimeout(EventInterface $event, int $timeoutSeconds = 30): EventInterface
    {
        $startTime = time();
        $eventName = $event->getName();
        $listeners = $this->getListeners($eventName);

        foreach ($listeners as $listener) {
            // Verificar timeout
            if (time() - $startTime >= $timeoutSeconds) {
                error_log("Event dispatch timeout for {$eventName} after {$timeoutSeconds} seconds");
                break;
            }

            if ($event->isCanceled()) {
                break;
            }

            try {
                $listener($event);
            } catch (\Throwable $e) {
                error_log("Event listener error for {$eventName}: " . $e->getMessage());
            }
        }

        return $event;
    }

    /**
     * Dispatch assíncrono (simples implementação com promises futuras)
     */
    public function dispatchAsync(EventInterface $event): EventInterface
    {
        // Para implementação futura com Laravel Queues ou similar
        // Por enquanto, executa síncronamente
        return $this->dispatch($event);
    }
}