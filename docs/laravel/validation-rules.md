# Validation Rules Brasileiras - Clubify Checkout SDK

Esta documentação detalha todas as regras de validação específicas do Brasil incluídas no Clubify Checkout SDK para Laravel, proporcionando validação robusta e conforme às normas brasileiras.

## 📋 Índice

- [Regras de Validação](#regras-de-validação)
  - [CPF](#cpf)
  - [CNPJ](#cnpj)
  - [CEP](#cep)
  - [Telefone](#telefone)
  - [Cartão de Crédito](#cartão-de-crédito)
  - [PIX](#pix)
  - [Banco](#banco)
  - [Data](#data)
- [Uso das Regras](#uso-das-regras)
- [Mensagens de Erro](#mensagens-de-erro)
- [Formatação](#formatação)
- [Exemplos Práticos](#exemplos-práticos)
- [Testes](#testes)

## Regras de Validação

### CPF

Validação completa de CPF (Cadastro de Pessoa Física) brasileiro.

```php
<?php

namespace ClubifyCheckout\Rules;

use Illuminate\Contracts\Validation\Rule;

class CpfValidation implements Rule
{
    public function passes($attribute, $value): bool
    {
        // Remove formatação
        $cpf = preg_replace('/[^0-9]/', '', $value);

        // Verifica se tem 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Verifica sequências inválidas
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Calcula primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($cpf[$i]) * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;

        // Verifica primeiro dígito
        if (intval($cpf[9]) !== $digit1) {
            return false;
        }

        // Calcula segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cpf[$i]) * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

        // Verifica segundo dígito
        return intval($cpf[10]) === $digit2;
    }

    public function message(): string
    {
        return 'O campo :attribute deve ser um CPF válido.';
    }
}
```

**Uso:**

```php
// Em Request
public function rules(): array
{
    return [
        'customer_cpf' => ['required', new CpfValidation()],
        'document' => ['sometimes', new CpfValidation()]
    ];
}

// Via Facade
use ClubifyCheckout\Facades\ClubifyValidation;

$isValid = ClubifyValidation::cpf('123.456.789-09');
$formatted = ClubifyValidation::formatCpf('12345678909'); // 123.456.789-09
```

### CNPJ

Validação de CNPJ (Cadastro Nacional de Pessoa Jurídica) brasileiro.

```php
<?php

namespace ClubifyCheckout\Rules;

use Illuminate\Contracts\Validation\Rule;

class CnpjValidation implements Rule
{
    public function passes($attribute, $value): bool
    {
        // Remove formatação
        $cnpj = preg_replace('/[^0-9]/', '', $value);

        // Verifica se tem 14 dígitos
        if (strlen($cnpj) !== 14) {
            return false;
        }

        // Verifica sequências inválidas
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // Calcula primeiro dígito verificador
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += intval($cnpj[$i]) * $weights1[$i];
        }
        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;

        // Verifica primeiro dígito
        if (intval($cnpj[12]) !== $digit1) {
            return false;
        }

        // Calcula segundo dígito verificador
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += intval($cnpj[$i]) * $weights2[$i];
        }
        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

        // Verifica segundo dígito
        return intval($cnpj[13]) === $digit2;
    }

    public function message(): string
    {
        return 'O campo :attribute deve ser um CNPJ válido.';
    }
}
```

### CEP

Validação de CEP (Código de Endereçamento Postal) brasileiro.

```php
<?php

namespace ClubifyCheckout\Rules;

use Illuminate\Contracts\Validation\Rule;

class CepValidation implements Rule
{
    public function passes($attribute, $value): bool
    {
        // Remove formatação
        $cep = preg_replace('/[^0-9]/', '', $value);

        // Verifica se tem 8 dígitos
        if (strlen($cep) !== 8) {
            return false;
        }

        // Verifica se não é sequência inválida
        if (preg_match('/^(0{8}|1{8}|2{8}|3{8}|4{8}|5{8}|6{8}|7{8}|8{8}|9{8})$/', $cep)) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return 'O campo :attribute deve ser um CEP válido.';
    }
}
```

### Telefone

Validação de números de telefone brasileiros (fixo e celular).

```php
<?php

namespace ClubifyCheckout\Rules;

use Illuminate\Contracts\Validation\Rule;

class TelefoneValidation implements Rule
{
    private bool $allowFixo;
    private bool $allowCelular;

    public function __construct(bool $allowFixo = true, bool $allowCelular = true)
    {
        $this->allowFixo = $allowFixo;
        $this->allowCelular = $allowCelular;
    }

    public function passes($attribute, $value): bool
    {
        // Remove formatação
        $phone = preg_replace('/[^0-9]/', '', $value);

        // Telefone com DDD (10 ou 11 dígitos)
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            return $this->validateWithDdd($phone);
        }

        // Telefone sem DDD (8 ou 9 dígitos)
        if (strlen($phone) === 8 || strlen($phone) === 9) {
            return $this->validateWithoutDdd($phone);
        }

        return false;
    }

    private function validateWithDdd(string $phone): bool
    {
        $ddd = intval(substr($phone, 0, 2));
        $number = substr($phone, 2);

        // Verifica se DDD é válido
        if (!$this->isValidDdd($ddd)) {
            return false;
        }

        // Telefone fixo (8 dígitos)
        if (strlen($number) === 8) {
            if (!$this->allowFixo) return false;

            // Primeiro dígito deve ser 2, 3, 4 ou 5
            $firstDigit = intval($number[0]);
            return in_array($firstDigit, [2, 3, 4, 5]);
        }

        // Telefone celular (9 dígitos)
        if (strlen($number) === 9) {
            if (!$this->allowCelular) return false;

            // Primeiro dígito deve ser 9
            return $number[0] === '9';
        }

        return false;
    }

    private function validateWithoutDdd(string $phone): bool
    {
        // Telefone fixo (8 dígitos)
        if (strlen($phone) === 8) {
            if (!$this->allowFixo) return false;

            $firstDigit = intval($phone[0]);
            return in_array($firstDigit, [2, 3, 4, 5]);
        }

        // Telefone celular (9 dígitos)
        if (strlen($phone) === 9) {
            if (!$this->allowCelular) return false;

            return $phone[0] === '9';
        }

        return false;
    }

    private function isValidDdd(int $ddd): bool
    {
        $validDdds = [
            11, 12, 13, 14, 15, 16, 17, 18, 19, // SP
            21, 22, 24, // RJ/ES
            27, 28, // ES
            31, 32, 33, 34, 35, 37, 38, // MG
            41, 42, 43, 44, 45, 46, // PR
            47, 48, 49, // SC
            51, 53, 54, 55, // RS
            61, // DF/GO
            62, 64, // GO
            63, // TO
            65, 66, // MT
            67, // MS
            68, // AC
            69, // RO
            71, 73, 74, 75, 77, // BA
            79, // SE
            81, 87, // PE
            82, // AL
            83, // PB
            84, // RN
            85, 88, // CE
            86, 89, // PI
            91, 93, 94, // PA
            92, 97, // AM
            95, // RR
            96, // AP
            98, 99 // MA
        ];

        return in_array($ddd, $validDdds);
    }

    public function message(): string
    {
        if ($this->allowFixo && $this->allowCelular) {
            return 'O campo :attribute deve ser um telefone válido (fixo ou celular).';
        } elseif ($this->allowFixo) {
            return 'O campo :attribute deve ser um telefone fixo válido.';
        } else {
            return 'O campo :attribute deve ser um telefone celular válido.';
        }
    }
}
```

### Cartão de Crédito

Validação específica para cartões brasileiros.

```php
<?php

namespace ClubifyCheckout\Rules;

use Illuminate\Contracts\Validation\Rule;

class CartaoValidation implements Rule
{
    private array $allowedBrands;

    public function __construct(array $allowedBrands = [])
    {
        $this->allowedBrands = $allowedBrands ?: ['visa', 'mastercard', 'amex', 'elo', 'hipercard'];
    }

    public function passes($attribute, $value): bool
    {
        // Remove formatação
        $number = preg_replace('/[^0-9]/', '', $value);

        // Verifica Luhn algorithm
        if (!$this->luhnCheck($number)) {
            return false;
        }

        // Verifica se a bandeira é permitida
        $brand = $this->detectBrand($number);
        return in_array($brand, $this->allowedBrands);
    }

    private function luhnCheck(string $number): bool
    {
        $sum = 0;
        $length = strlen($number);

        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = intval($number[$i]);

            if (($length - $i) % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    private function detectBrand(string $number): string
    {
        // Visa
        if (preg_match('/^4[0-9]{12,18}$/', $number)) {
            return 'visa';
        }

        // Mastercard
        if (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
            return 'mastercard';
        }

        // American Express
        if (preg_match('/^3[47][0-9]{13}$/', $number)) {
            return 'amex';
        }

        // Elo (Brazilian brand)
        if (preg_match('/^(4011|4312|4389|4514|4573|6277|6362|6363|6504|6516|6550)[0-9]{12}$/', $number)) {
            return 'elo';
        }

        // Hipercard (Brazilian brand)
        if (preg_match('/^(606282|3841)[0-9]{10,13}$/', $number)) {
            return 'hipercard';
        }

        return 'unknown';
    }

    public function message(): string
    {
        return 'O campo :attribute deve ser um cartão de crédito válido das bandeiras: ' . implode(', ', $this->allowedBrands) . '.';
    }
}
```

### PIX

Validação de chaves PIX brasileiras.

```php
<?php

namespace ClubifyCheckout\Rules;

use Illuminate\Contracts\Validation\Rule;

class PixValidation implements Rule
{
    public function passes($attribute, $value): bool
    {
        $key = trim($value);

        // Email
        if (filter_var($key, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        // CPF
        if ((new CpfValidation())->passes($attribute, $key)) {
            return true;
        }

        // CNPJ
        if ((new CnpjValidation())->passes($attribute, $key)) {
            return true;
        }

        // Telefone
        if ((new TelefoneValidation())->passes($attribute, $key)) {
            return true;
        }

        // Chave aleatória (UUID)
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return true;
        }

        return false;
    }

    public function message(): string
    {
        return 'O campo :attribute deve ser uma chave PIX válida (CPF, CNPJ, email, telefone ou chave aleatória).';
    }
}
```

### Banco

Validação de dados bancários brasileiros.

```php
<?php

namespace ClubifyCheckout\Rules;

use Illuminate\Contracts\Validation\Rule;

class ContaBancariaValidation implements Rule
{
    public function passes($attribute, $value): bool
    {
        $data = is_array($value) ? $value : json_decode($value, true);

        if (!is_array($data)) {
            return false;
        }

        // Verifica campos obrigatórios
        $required = ['banco', 'agencia', 'conta'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }

        // Valida código do banco (3 dígitos)
        if (!preg_match('/^[0-9]{3}$/', $data['banco'])) {
            return false;
        }

        // Valida agência (4-5 dígitos, pode ter dígito verificador)
        if (!preg_match('/^[0-9]{4,5}(-?[0-9])?$/', $data['agencia'])) {
            return false;
        }

        // Valida conta (até 12 dígitos, pode ter dígito verificador)
        if (!preg_match('/^[0-9]{1,12}(-?[0-9X])?$/', $data['conta'])) {
            return false;
        }

        // Verifica se o banco existe na lista do BACEN
        return $this->isValidBank($data['banco']);
    }

    private function isValidBank(string $code): bool
    {
        $validBanks = [
            '001', // Banco do Brasil
            '033', // Santander
            '104', // Caixa Econômica Federal
            '237', // Bradesco
            '341', // Itaú
            '260', // Nubank
            '077', // Inter
            '212', // Banco Original
            '290', // PagSeguro
            '323', // Mercado Pago
            // Adicione mais códigos conforme necessário
        ];

        return in_array($code, $validBanks);
    }

    public function message(): string
    {
        return 'O campo :attribute deve conter dados bancários válidos (banco, agência e conta).';
    }
}
```

### Data

Validação de datas brasileiras.

```php
<?php

namespace ClubifyCheckout\Rules;

use Illuminate\Contracts\Validation\Rule;
use Carbon\Carbon;

class DataBrasileiraValidation implements Rule
{
    private ?Carbon $minDate;
    private ?Carbon $maxDate;

    public function __construct(?string $minDate = null, ?string $maxDate = null)
    {
        $this->minDate = $minDate ? Carbon::parse($minDate) : null;
        $this->maxDate = $maxDate ? Carbon::parse($maxDate) : null;
    }

    public function passes($attribute, $value): bool
    {
        // Formatos aceitos: dd/mm/yyyy, dd-mm-yyyy, yyyy-mm-dd
        $patterns = [
            '/^(\d{2})\/(\d{2})\/(\d{4})$/', // dd/mm/yyyy
            '/^(\d{2})-(\d{2})-(\d{4})$/',   // dd-mm-yyyy
            '/^(\d{4})-(\d{2})-(\d{2})$/'   // yyyy-mm-dd
        ];

        $date = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $matches)) {
                try {
                    if (count($matches) === 4) {
                        if (strlen($matches[1]) === 4) {
                            // yyyy-mm-dd
                            $date = Carbon::createFromDate($matches[1], $matches[2], $matches[3]);
                        } else {
                            // dd/mm/yyyy ou dd-mm-yyyy
                            $date = Carbon::createFromDate($matches[3], $matches[2], $matches[1]);
                        }
                    }
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        if (!$date) {
            return false;
        }

        // Verifica limites de data
        if ($this->minDate && $date->lt($this->minDate)) {
            return false;
        }

        if ($this->maxDate && $date->gt($this->maxDate)) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        $message = 'O campo :attribute deve ser uma data válida no formato dd/mm/aaaa.';

        if ($this->minDate && $this->maxDate) {
            $message .= ' A data deve estar entre ' . $this->minDate->format('d/m/Y') . ' e ' . $this->maxDate->format('d/m/Y') . '.';
        } elseif ($this->minDate) {
            $message .= ' A data deve ser posterior a ' . $this->minDate->format('d/m/Y') . '.';
        } elseif ($this->maxDate) {
            $message .= ' A data deve ser anterior a ' . $this->maxDate->format('d/m/Y') . '.';
        }

        return $message;
    }
}
```

## Uso das Regras

### Em Form Requests

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use ClubifyCheckout\Rules\{CpfValidation, CnpjValidation, CepValidation, TelefoneValidation};

class CustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Documentos
            'cpf' => ['sometimes', new CpfValidation()],
            'cnpj' => ['sometimes', new CnpjValidation()],

            // Endereço
            'cep' => ['required', new CepValidation()],

            // Contato
            'telefone' => ['required', new TelefoneValidation()],
            'celular' => ['sometimes', new TelefoneValidation(false, true)], // Apenas celular

            // Dados bancários
            'conta_bancaria' => ['sometimes', new ContaBancariaValidation()],

            // PIX
            'chave_pix' => ['sometimes', new PixValidation()],

            // Data de nascimento
            'data_nascimento' => ['required', new DataBrasileiraValidation('1900-01-01', 'today')]
        ];
    }
}
```

### Via Facade

```php
use ClubifyCheckout\Facades\ClubifyValidation;

// Validações individuais
$cpfValido = ClubifyValidation::cpf('123.456.789-09');
$cnpjValido = ClubifyValidation::cnpj('12.345.678/0001-90');
$cepValido = ClubifyValidation::cep('01310-100');
$telefoneValido = ClubifyValidation::telefone('(11) 99999-9999');

// Formatação
$cpfFormatado = ClubifyValidation::formatCpf('12345678909');
$cnpjFormatado = ClubifyValidation::formatCnpj('12345678000190');
$cepFormatado = ClubifyValidation::formatCep('01310100');
$telefoneFormatado = ClubifyValidation::formatTelefone('11999999999');

// Detecção de tipo de documento
$tipoDocumento = ClubifyValidation::detectDocumentType('12345678909'); // 'cpf'
```

### Validação Condicional

```php
public function rules(): array
{
    return [
        'tipo_pessoa' => ['required', 'in:fisica,juridica'],
        'documento' => [
            'required',
            function ($attribute, $value, $fail) {
                $tipoPessoa = $this->input('tipo_pessoa');

                if ($tipoPessoa === 'fisica') {
                    $rule = new CpfValidation();
                } else {
                    $rule = new CnpjValidation();
                }

                if (!$rule->passes($attribute, $value)) {
                    $fail($rule->message());
                }
            }
        ]
    ];
}
```

## Mensagens de Erro

### Personalização de Mensagens

```php
// resources/lang/pt_BR/validation.php
return [
    'custom' => [
        'cpf' => [
            'cpf_validation' => 'O CPF informado não é válido.',
        ],
        'cnpj' => [
            'cnpj_validation' => 'O CNPJ informado não é válido.',
        ],
        'cep' => [
            'cep_validation' => 'O CEP deve ter 8 dígitos no formato 12345-678.',
        ],
        'telefone' => [
            'telefone_validation' => 'O telefone deve estar no formato (11) 99999-9999.',
        ]
    ]
];
```

### Mensagens Dinâmicas

```php
class CustomCpfValidation extends CpfValidation
{
    public function message(): string
    {
        return 'O CPF informado (:input) não é válido. Verifique os números e tente novamente.';
    }
}
```

## Formatação

### Helpers de Formatação

```php
<?php

namespace ClubifyCheckout\Helpers;

class BrazilianFormatter
{
    public static function cpf(string $cpf): string
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    public static function cnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }

    public static function cep(string $cep): string
    {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
    }

    public static function telefone(string $telefone): string
    {
        $telefone = preg_replace('/[^0-9]/', '', $telefone);

        if (strlen($telefone) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
        } elseif (strlen($telefone) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
        }

        return $telefone;
    }

    public static function currency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
```

## Exemplos Práticos

### 1. Formulário de Checkout Completo

```php
class CheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Dados do cliente
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'email'],
            'customer.cpf' => ['required', new CpfValidation()],
            'customer.phone' => ['required', new TelefoneValidation()],
            'customer.birth_date' => ['required', new DataBrasileiraValidation('1900-01-01', '-18 years')],

            // Endereço
            'address.cep' => ['required', new CepValidation()],
            'address.street' => ['required', 'string', 'max:255'],
            'address.number' => ['required', 'string', 'max:10'],
            'address.complement' => ['sometimes', 'string', 'max:100'],
            'address.neighborhood' => ['required', 'string', 'max:100'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.state' => ['required', 'string', 'size:2'],

            // Pagamento
            'payment.method' => ['required', 'in:credit_card,pix,boleto'],
            'payment.card_number' => ['required_if:payment.method,credit_card', new CartaoValidation()],
            'payment.card_holder' => ['required_if:payment.method,credit_card', 'string'],
            'payment.card_expiry' => ['required_if:payment.method,credit_card', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'payment.card_cvv' => ['required_if:payment.method,credit_card', 'digits:3'],
            'payment.pix_key' => ['required_if:payment.method,pix', new PixValidation()]
        ];
    }

    public function messages(): array
    {
        return [
            'customer.birth_date.data_brasileira_validation' => 'O cliente deve ser maior de 18 anos.',
            'payment.card_expiry.regex' => 'A data de validade deve estar no formato MM/AA.',
        ];
    }
}
```

### 2. Validação de Pessoa Jurídica

```php
class CompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cnpj' => ['required', new CnpjValidation()],
            'razao_social' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['sometimes', 'string', 'max:255'],
            'inscricao_estadual' => ['sometimes', 'string', 'max:20'],
            'telefone' => ['required', new TelefoneValidation()],
            'email' => ['required', 'email'],
            'cep' => ['required', new CepValidation()],
            'conta_bancaria' => ['required', new ContaBancariaValidation()],
            'chave_pix' => ['sometimes', new PixValidation()]
        ];
    }
}
```

### 3. Middleware de Validação

```php
class ValidateDocumentMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->has('document')) {
            $document = $request->input('document');
            $type = ClubifyValidation::detectDocumentType($document);

            if ($type === 'unknown') {
                return response()->json(['error' => 'Documento inválido'], 400);
            }

            $request->merge(['document_type' => $type]);
        }

        return $next($request);
    }
}
```

## Testes

### Testes de Unidade

```php
<?php

namespace Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ClubifyCheckout\Rules\CpfValidation;

class CpfValidationTest extends TestCase
{
    public function test_valid_cpf()
    {
        $rule = new CpfValidation();

        $this->assertTrue($rule->passes('cpf', '123.456.789-09'));
        $this->assertTrue($rule->passes('cpf', '12345678909'));
    }

    public function test_invalid_cpf()
    {
        $rule = new CpfValidation();

        $this->assertFalse($rule->passes('cpf', '123.456.789-00'));
        $this->assertFalse($rule->passes('cpf', '111.111.111-11'));
        $this->assertFalse($rule->passes('cpf', '123.456.78'));
    }
}
```

### Testes de Integração

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use ClubifyCheckout\Rules\CnpjValidation;

class BrazilianValidationTest extends TestCase
{
    public function test_checkout_with_brazilian_data()
    {
        $data = [
            'customer' => [
                'name' => 'João da Silva',
                'cpf' => '123.456.789-09',
                'phone' => '(11) 99999-9999'
            ],
            'address' => [
                'cep' => '01310-100'
            ]
        ];

        $response = $this->postJson('/api/checkout', $data);

        $response->assertStatus(200);
    }

    public function test_validation_errors()
    {
        $data = [
            'customer' => [
                'cpf' => '123.456.789-00', // CPF inválido
                'phone' => '999999999' // Telefone inválido
            ]
        ];

        $response = $this->postJson('/api/checkout', $data);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['customer.cpf', 'customer.phone']);
    }
}
```

---

## Conclusão

O sistema de Validation Rules brasileiras do Clubify Checkout SDK oferece:

- **Validação Completa**: Cobertura de todos os documentos e formatos brasileiros
- **Flexibilidade**: Regras customizáveis para diferentes necessidades
- **Performance**: Validações otimizadas sem consultas externas
- **Usabilidade**: Interface simples e mensagens claras
- **Manutenibilidade**: Código limpo seguindo padrões Laravel

Essas validações garantem que sua aplicação processe apenas dados válidos e bem formatados, melhorando a experiência do usuário e reduzindo erros de processamento.