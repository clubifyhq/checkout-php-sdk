<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de Passkey/WebAuthn
 *
 * Representa uma credencial WebAuthn associada a um usuário,
 * incluindo metadados de segurança e informações do dispositivo.
 */
class PasskeyData extends BaseData
{
    public string $id;
    public string $user_id;
    public string $credential_id;
    public string $public_key;
    public int $sign_count = 0;
    public string $name;
    public ?string $device_type = null;
    public ?string $device_name = null;
    public bool $is_cross_platform = false;
    public bool $is_backup_eligible = false;
    public bool $is_backup_state = false;
    public ?DateTime $last_used_at = null;
    public ?DateTime $created_at = null;
    public array $metadata = [];
    public string $attestation_type = 'none';
    public ?string $aaguid = null;
    public ?array $transports = null;

    /**
     * Regras de validação
     */
    public function getRules(): array
    {
        return [
            'id' => ['string'],
            'user_id' => ['required', 'string'],
            'credential_id' => ['required', 'string'],
            'public_key' => ['required', 'string'],
            'sign_count' => ['integer', 'min:0'],
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'is_cross_platform' => ['boolean'],
            'is_backup_eligible' => ['boolean'],
            'is_backup_state' => ['boolean'],
            'last_used_at' => ['nullable', 'date'],
            'created_at' => ['nullable', 'date'],
            'metadata' => ['array'],
            'attestation_type' => ['string', 'max:20'],
            'aaguid' => ['nullable', 'string'],
            'transports' => ['nullable', 'array'],
        ];
    }

    /**
     * Verifica se o passkey foi usado recentemente
     */
    public function wasUsedRecently(int $hours = 24): bool
    {
        if (!$this->last_used_at) {
            return false;
        }

        $threshold = new DateTime("-{$hours} hours");
        return $this->last_used_at > $threshold;
    }

    /**
     * Verifica se é um passkey de plataforma (Touch ID, Face ID, Windows Hello)
     */
    public function isPlatformAuthenticator(): bool
    {
        return !$this->is_cross_platform;
    }

    /**
     * Verifica se é um security key (USB, NFC, Bluetooth)
     */
    public function isSecurityKey(): bool
    {
        return $this->is_cross_platform;
    }

    /**
     * Obtém dados seguros (sem chave pública)
     */
    public function toSafeArray(): array
    {
        $data = $this->toArray();

        // Remover dados sensíveis
        unset($data['public_key']);
        unset($data['credential_id']);

        return $data;
    }

    /**
     * Obtém informações do dispositivo
     */
    public function getDeviceInfo(): array
    {
        return [
            'type' => $this->device_type,
            'name' => $this->device_name,
            'is_platform' => $this->isPlatformAuthenticator(),
            'is_security_key' => $this->isSecurityKey(),
            'transports' => $this->transports,
            'backup_eligible' => $this->is_backup_eligible,
            'backup_state' => $this->is_backup_state,
        ];
    }
}
