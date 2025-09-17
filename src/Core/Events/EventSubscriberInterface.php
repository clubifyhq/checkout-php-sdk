<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Events;

interface EventSubscriberInterface
{
    /**
     * Retorna array de eventos que este subscriber escuta
     *
     * @return array Array no formato:
     * [
     *     'event.name' => 'methodName',
     *     'other.event' => ['methodName', priority],
     *     'another.event' => [['method1', priority1], ['method2', priority2]]
     * ]
     */
    public static function getSubscribedEvents(): array;
}
