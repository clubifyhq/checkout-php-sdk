<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Events;

/**
 * Implementação básica de um evento
 */
class Event implements EventInterface
{
    private string $name;
    private array $data;
    private int $timestamp;
    private bool $canceled = false;
    private array $context = [];

    public function __construct(string $name, array $data = [], array $context = [])
    {
        $this->name = $name;
        $this->data = $data;
        $this->timestamp = time();
        $this->context = $context;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    public function cancel(): void
    {
        $this->canceled = true;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Adicionar dados ao evento
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Adicionar item aos dados do evento
     */
    public function addData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Obter item específico dos dados
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Verificar se tem chave nos dados
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Converter evento para array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
            'canceled' => $this->canceled,
            'context' => $this->context,
        ];
    }

    /**
     * Criar evento a partir de array
     */
    public static function fromArray(array $data): self
    {
        $event = new self(
            $data['name'],
            $data['data'] ?? [],
            $data['context'] ?? []
        );

        if (isset($data['timestamp'])) {
            $event->timestamp = $data['timestamp'];
        }

        if (isset($data['canceled']) && $data['canceled']) {
            $event->cancel();
        }

        return $event;
    }
}
