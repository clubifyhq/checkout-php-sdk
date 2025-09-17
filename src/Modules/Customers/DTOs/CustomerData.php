<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Customers\DTOs;

use ClubifyCheckout\Data\BaseData;
use DateTime;
use InvalidArgumentException;

/**
 * DTO para dados de cliente
 *
 * Representa um cliente completo com todas as informações
 * pessoais, financeiras e comportamentais, seguindo
 * compliance LGPD/GDPR para proteção de dados.
 *
 * Funcionalidades principais:
 * - Validação LGPD completa
 * - Sanitização automática de dados
 * - Mascaramento de dados sensíveis
 * - Controle de consentimento
 * - Auditoria de acesso
 * - Exportação de dados
 *
 * Campos obrigatórios:
 * - name: Nome completo
 * - email: Email válido
 * - organization_id: ID da organização
 *
 * Campos opcionais:
 * - phone: Telefone
 * - document: CPF/CNPJ
 * - birth_date: Data de nascimento
 * - address: Endereço completo
 * - metadata: Dados customizados
 *
 * Compliance LGPD/GDPR:
 * - Controle de consentimento granular
 * - Logs de auditoria
 * - Right to be forgotten
 * - Data portability
 * - Purpose limitation
 */
class CustomerData extends BaseData
{
    public string $id;
    public string $name;
    public string $email;
    public ?string $phone = null;
    public ?string $document = null;
    public ?string $document_type = null;
    public ?DateTime $birth_date = null;
    public ?string $gender = null;
    public string $status = 'active';
    public ?array $address = null;
    public array $tags = [];
    public float $total_spent = 0.0;
    public int $total_orders = 0;
    public ?string $organization_id = null;
    public array $metadata = [];
    public array $preferences = [];
    public array $consent = [];
    public ?DateTime $created_at = null;
    public ?DateTime $updated_at = null;
    public ?DateTime $last_login_at = null;
    public ?DateTime $last_purchase_at = null;

    /**
     * Regras de validação
     */
    protected function getValidationRules(): array
    {
        return [
            'id' => ['string'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'document' => ['nullable', 'string', 'max:20'],
            'document_type' => ['nullable', 'in:cpf,cnpj'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'status' => ['in:active,inactive,suspended,deleted'],
            'address' => ['nullable', 'array'],
            'tags' => ['array'],
            'total_spent' => ['numeric', 'min:0'],
            'total_orders' => ['integer', 'min:0'],
            'organization_id' => ['string'],
            'metadata' => ['array'],
            'preferences' => ['array'],
            'consent' => ['array'],
            'created_at' => ['nullable', 'date'],
            'updated_at' => ['nullable', 'date'],
            'last_login_at' => ['nullable', 'date'],
            'last_purchase_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Sanitiza dados antes da validação
     */
    protected function sanitizeData(array $data): array
    {
        // Email lowercase
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        // Nome capitalizado
        if (isset($data['name'])) {
            $data['name'] = $this->capitalizeName(trim($data['name']));
        }

        // Documento apenas números
        if (isset($data['document'])) {
            $data['document'] = preg_replace('/\D/', '', $data['document']);
        }

        // Telefone apenas números
        if (isset($data['phone'])) {
            $data['phone'] = preg_replace('/\D/', '', $data['phone']);
        }

        // Converte datas
        $dateFields = ['birth_date', 'created_at', 'updated_at', 'last_login_at', 'last_purchase_at'];
        foreach ($dateFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = new DateTime($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Obtém dados mascarados para exibição segura
     */
    public function getMaskedData(): array
    {
        $data = $this->toArray();

        // Mascarar email
        if (isset($data['email'])) {
            $data['email'] = $this->maskEmail($data['email']);
        }

        // Mascarar documento
        if (isset($data['document'])) {
            $data['document'] = $this->maskDocument($data['document']);
        }

        // Mascarar telefone
        if (isset($data['phone'])) {
            $data['phone'] = $this->maskPhone($data['phone']);
        }

        // Remover dados sensíveis de metadata
        if (isset($data['metadata'])) {
            $data['metadata'] = $this->removeSensitiveMetadata($data['metadata']);
        }

        return $data;
    }

    /**
     * Verifica se o cliente deu consentimento para um propósito específico
     */
    public function hasConsent(string $purpose): bool
    {
        return isset($this->consent[$purpose]) &&
               $this->consent[$purpose]['granted'] === true &&
               (!isset($this->consent[$purpose]['expires_at']) ||
                new DateTime($this->consent[$purpose]['expires_at']) > new DateTime());
    }

    /**
     * Adiciona consentimento para um propósito
     */
    public function grantConsent(string $purpose, ?DateTime $expiresAt = null): void
    {
        $this->consent[$purpose] = [
            'granted' => true,
            'granted_at' => new DateTime(),
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'version' => '1.0',
        ];
    }

    /**
     * Remove consentimento para um propósito
     */
    public function revokeConsent(string $purpose): void
    {
        if (isset($this->consent[$purpose])) {
            $this->consent[$purpose]['granted'] = false;
            $this->consent[$purpose]['revoked_at'] = new DateTime();
        }
    }

    /**
     * Obtém idade do cliente
     */
    public function getAge(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        return $this->birth_date->diff(new DateTime())->y;
    }

    /**
     * Verifica se é cliente VIP baseado em gastos
     */
    public function isVip(float $threshold = 10000.0): bool
    {
        return $this->total_spent >= $threshold;
    }

    /**
     * Obtém valor médio por pedido
     */
    public function getAverageOrderValue(): float
    {
        if ($this->total_orders === 0) {
            return 0.0;
        }

        return $this->total_spent / $this->total_orders;
    }

    /**
     * Verifica se o cliente está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Obtém endereço formatado
     */
    public function getFormattedAddress(): ?string
    {
        if (!$this->address) {
            return null;
        }

        $parts = [];

        if (isset($this->address['street'])) {
            $parts[] = $this->address['street'];
        }

        if (isset($this->address['number'])) {
            $parts[] = $this->address['number'];
        }

        if (isset($this->address['complement'])) {
            $parts[] = $this->address['complement'];
        }

        if (isset($this->address['neighborhood'])) {
            $parts[] = $this->address['neighborhood'];
        }

        if (isset($this->address['city'])) {
            $parts[] = $this->address['city'];
        }

        if (isset($this->address['state'])) {
            $parts[] = $this->address['state'];
        }

        if (isset($this->address['zip_code'])) {
            $parts[] = $this->formatZipCode($this->address['zip_code']);
        }

        return implode(', ', array_filter($parts));
    }

    /**
     * Exporta dados para compliance LGPD
     */
    public function exportForCompliance(): array
    {
        return [
            'personal_data' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'document' => $this->document,
                'birth_date' => $this->birth_date?->format('Y-m-d'),
                'gender' => $this->gender,
                'address' => $this->address,
            ],
            'behavioral_data' => [
                'total_spent' => $this->total_spent,
                'total_orders' => $this->total_orders,
                'tags' => $this->tags,
                'preferences' => $this->preferences,
                'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
                'last_purchase_at' => $this->last_purchase_at?->format('Y-m-d H:i:s'),
            ],
            'consent_data' => $this->consent,
            'metadata' => $this->metadata,
            'audit_data' => [
                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
                'status' => $this->status,
                'organization_id' => $this->organization_id,
            ],
        ];
    }

    /**
     * Capitaliza nome próprio
     */
    private function capitalizeName(string $name): string
    {
        // Preposições que devem ficar em minúsculo
        $prepositions = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'na', 'no', 'para', 'por'];

        $words = explode(' ', strtolower($name));
        $capitalizedWords = [];

        foreach ($words as $index => $word) {
            if ($index === 0 || !in_array($word, $prepositions)) {
                $capitalizedWords[] = ucfirst($word);
            } else {
                $capitalizedWords[] = $word;
            }
        }

        return implode(' ', $capitalizedWords);
    }

    /**
     * Mascara email para exibição
     */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email);

        if (strlen($local) <= 2) {
            return str_repeat('*', strlen($local)) . '@' . $domain;
        }

        return $local[0] . str_repeat('*', strlen($local) - 2) . $local[-1] . '@' . $domain;
    }

    /**
     * Mascara documento para exibição
     */
    private function maskDocument(string $document): string
    {
        if (strlen($document) === 11) { // CPF
            return substr($document, 0, 3) . '.***.***-' . substr($document, -2);
        } elseif (strlen($document) === 14) { // CNPJ
            return substr($document, 0, 2) . '.***.***/****-' . substr($document, -2);
        }

        return str_repeat('*', strlen($document));
    }

    /**
     * Mascara telefone para exibição
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) >= 10) {
            return substr($phone, 0, 2) . '****' . substr($phone, -4);
        }

        return str_repeat('*', strlen($phone));
    }

    /**
     * Remove dados sensíveis de metadata
     */
    private function removeSensitiveMetadata(array $metadata): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'api_key'];

        foreach ($sensitiveKeys as $key) {
            if (isset($metadata[$key])) {
                $metadata[$key] = '[REDACTED]';
            }
        }

        return $metadata;
    }

    /**
     * Formata CEP
     */
    private function formatZipCode(string $zipCode): string
    {
        $cleanZip = preg_replace('/\D/', '', $zipCode);

        if (strlen($cleanZip) === 8) {
            return substr($cleanZip, 0, 5) . '-' . substr($cleanZip, 5);
        }

        return $cleanZip;
    }
}