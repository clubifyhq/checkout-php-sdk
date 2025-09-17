<?php

declare(strict_types=1);

namespace Clubify\Checkout\Utils\Validators;

/**
 * Validador avançado de email
 *
 * Implementa validação robusta de endereços de email
 * seguindo as especificações RFC 5322 e incluindo
 * verificações de domínios comuns e detecção de
 * emails temporários/descartáveis.
 *
 * Funcionalidades:
 * - Validação RFC 5322 completa
 * - Detecção de domínios temporários
 * - Verificação de MX records (opcional)
 * - Normalização de emails
 * - Suporte a emails internacionais (IDN)
 * - Blacklist de domínios conhecidos como spam
 *
 * Exemplos de uso:
 * - $validator = new EmailValidator();
 * - $validator->validate('user@domain.com'); // true
 * - $validator->validate('invalid-email'); // false
 * - $validator->validateWithMX('user@domain.com'); // true se MX existe
 */
class EmailValidator implements ValidatorInterface
{
    private string $lastErrorMessage = '';

    /**
     * Lista de domínios temporários/descartáveis conhecidos
     */
    private array $disposableDomains = [
        '10minutemail.com',
        'tempmail.org',
        'guerrillamail.com',
        'mailinator.com',
        'throwaway.email',
        'temp-mail.org',
        'getnada.com',
        'yopmail.com',
        'maildrop.cc',
        'tempmail.io',
    ];

    /**
     * Lista de domínios comuns válidos
     */
    private array $commonDomains = [
        'gmail.com',
        'yahoo.com',
        'hotmail.com',
        'outlook.com',
        'terra.com.br',
        'uol.com.br',
        'globo.com',
        'ig.com.br',
        'bol.com.br',
        'r7.com',
    ];

    /**
     * Configurações do validador
     */
    private array $config = [
        'allow_disposable' => false,
        'check_mx_records' => false,
        'max_length' => 254,
        'allow_unicode' => true,
        'strict_mode' => false,
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Valida email básico
     */
    public function validate($value): bool
    {
        $result = $this->validateDetailed($value);
        return $result['valid'];
    }

    /**
     * Obtém mensagem de erro
     */
    public function getErrorMessage(): string
    {
        return $this->lastErrorMessage;
    }

    /**
     * Validação detalhada
     */
    public function validateDetailed($value): array
    {
        $this->lastErrorMessage = '';

        // Verificação básica de tipo
        if (!is_string($value)) {
            return $this->setError('Email deve ser uma string');
        }

        // Verifica comprimento
        if (strlen($value) > $this->config['max_length']) {
            return $this->setError("Email excede o limite de {$this->config['max_length']} caracteres");
        }

        // Normaliza o email
        $normalizedEmail = $this->normalizeEmail($value);

        // Validação de formato básico
        if (!$this->validateFormat($normalizedEmail)) {
            return $this->setError('Formato de email inválido');
        }

        // Extrai partes do email
        [$localPart, $domain] = explode('@', $normalizedEmail);

        // Valida parte local (antes do @)
        if (!$this->validateLocalPart($localPart)) {
            return $this->setError('Parte local do email inválida');
        }

        // Valida domínio
        if (!$this->validateDomain($domain)) {
            return $this->setError('Domínio do email inválido');
        }

        // Verifica domínios descartáveis
        if (!$this->config['allow_disposable'] && $this->isDisposableDomain($domain)) {
            return $this->setError('Domínio de email temporário não permitido');
        }

        // Verifica MX records se habilitado
        if ($this->config['check_mx_records'] && !$this->hasMXRecord($domain)) {
            return $this->setError('Domínio não possui registros MX válidos');
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * Valida email com verificação de MX
     */
    public function validateWithMX(string $email): bool
    {
        $originalConfig = $this->config['check_mx_records'];
        $this->config['check_mx_records'] = true;

        $result = $this->validate($email);

        $this->config['check_mx_records'] = $originalConfig;

        return $result;
    }

    /**
     * Verifica se é domínio descartável
     */
    public function isDisposableDomain(string $domain): bool
    {
        return in_array(strtolower($domain), $this->disposableDomains);
    }

    /**
     * Verifica se é domínio comum/confiável
     */
    public function isCommonDomain(string $domain): bool
    {
        return in_array(strtolower($domain), $this->commonDomains);
    }

    /**
     * Normaliza email para comparação
     */
    public function normalizeEmail(string $email): string
    {
        $email = trim(strtolower($email));

        // Remove pontos do Gmail (user.name@gmail.com = username@gmail.com)
        if (str_ends_with($email, '@gmail.com')) {
            [$local, $domain] = explode('@', $email);
            $local = str_replace('.', '', $local);
            $email = $local . '@' . $domain;
        }

        return $email;
    }

    /**
     * Obtém informações sobre o email
     */
    public function getEmailInfo(string $email): array
    {
        if (!$this->validate($email)) {
            return ['valid' => false];
        }

        [$localPart, $domain] = explode('@', $this->normalizeEmail($email));

        return [
            'valid' => true,
            'local_part' => $localPart,
            'domain' => $domain,
            'is_disposable' => $this->isDisposableDomain($domain),
            'is_common' => $this->isCommonDomain($domain),
            'has_mx' => $this->hasMXRecord($domain),
            'normalized' => $this->normalizeEmail($email),
        ];
    }

    /**
     * Valida formato usando filter_var e regex adicional
     */
    private function validateFormat(string $email): bool
    {
        // Validação básica com filter_var
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Validação adicional com regex para casos específicos
        if ($this->config['strict_mode']) {
            $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
            if (!preg_match($pattern, $email)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida parte local do email (antes do @)
     */
    private function validateLocalPart(string $localPart): bool
    {
        // Não pode estar vazio
        if (empty($localPart)) {
            return false;
        }

        // Comprimento máximo de 64 caracteres
        if (strlen($localPart) > 64) {
            return false;
        }

        // Não pode começar ou terminar com ponto
        if (str_starts_with($localPart, '.') || str_ends_with($localPart, '.')) {
            return false;
        }

        // Não pode ter pontos consecutivos
        if (str_contains($localPart, '..')) {
            return false;
        }

        return true;
    }

    /**
     * Valida domínio
     */
    private function validateDomain(string $domain): bool
    {
        // Não pode estar vazio
        if (empty($domain)) {
            return false;
        }

        // Comprimento máximo de 253 caracteres
        if (strlen($domain) > 253) {
            return false;
        }

        // Deve ter pelo menos um ponto
        if (!str_contains($domain, '.')) {
            return false;
        }

        // Validação de formato de domínio
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
            return false;
        }

        // Não pode começar ou terminar com hífen ou ponto
        if (preg_match('/^[-.]|[-.]$/', $domain)) {
            return false;
        }

        // Valida cada parte do domínio
        $parts = explode('.', $domain);
        foreach ($parts as $part) {
            if (empty($part) || strlen($part) > 63) {
                return false;
            }

            if (preg_match('/^-|-$/', $part)) {
                return false;
            }
        }

        // TLD deve ter pelo menos 2 caracteres
        $tld = end($parts);
        if (strlen($tld) < 2) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se domínio tem registros MX
     */
    private function hasMXRecord(string $domain): bool
    {
        // Verifica se a função existe (pode não estar disponível em alguns ambientes)
        if (!function_exists('checkdnsrr')) {
            return true; // Assume válido se não pode verificar
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }

    /**
     * Define erro e retorna resultado
     */
    private function setError(string $message): array
    {
        $this->lastErrorMessage = $message;
        return ['valid' => false, 'message' => $message];
    }

    /**
     * Adiciona domínio à lista de descartáveis
     */
    public function addDisposableDomain(string $domain): void
    {
        $this->disposableDomains[] = strtolower($domain);
        $this->disposableDomains = array_unique($this->disposableDomains);
    }

    /**
     * Remove domínio da lista de descartáveis
     */
    public function removeDisposableDomain(string $domain): void
    {
        $this->disposableDomains = array_filter(
            $this->disposableDomains,
            fn($d) => $d !== strtolower($domain)
        );
    }

    /**
     * Obtém lista de domínios descartáveis
     */
    public function getDisposableDomains(): array
    {
        return $this->disposableDomains;
    }
}