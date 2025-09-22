<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Config;

interface ConfigurationInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): self;

    public function has(string $key): bool;

    public function merge(array $config): self;

    public function toArray(): array;

    public function getEnvironment(): string;

    public function getTenantId(): ?string;

    public function getApiKey(): ?string;

    public function getBaseUrl(): string;

    public function getTimeout(): int;

    public function getMaxRetries(): int;

    public function isDebugEnabled(): bool;

    public function getDefaultHeaders(): array;

}
