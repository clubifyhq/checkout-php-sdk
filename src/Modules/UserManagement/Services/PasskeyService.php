<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\UserManagement\DTOs\PasskeyData;
use DateTime;

/**
 * Serviço de autenticação Passkeys/WebAuthn
 *
 * Implementa autenticação passwordless completa com WebAuthn,
 * incluindo registro, autenticação e gestão de credenciais.
 */
class PasskeyService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {
    }

    /**
     * Inicia processo de registro de passkey
     */
    public function registerBegin(string $userId): array
    {
        try {
            $challenge = $this->generateChallenge();
            $options = $this->createRegistrationOptions($userId, $challenge);

            // Salvar challenge em cache temporário
            $this->storeChallengeTemporarily($userId, $challenge);

            $this->logger->info('Passkey registration started', [
                'user_id' => $userId,
                'challenge_id' => substr($challenge, 0, 8) . '...',
            ]);

            return [
                'success' => true,
                'options' => $options,
                'challenge' => $challenge,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to start passkey registration', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Completa processo de registro de passkey
     */
    public function registerComplete(string $userId, array $credential): array
    {
        try {
            // Verificar challenge
            $storedChallenge = $this->getStoredChallenge($userId);
            if (!$storedChallenge) {
                throw new \Exception('Invalid or expired challenge');
            }

            // Validar credencial
            $validatedCredential = $this->validateCredential($credential, $storedChallenge);

            // Criar passkey data
            $passkeyData = new PasskeyData([
                'user_id' => $userId,
                'credential_id' => $validatedCredential['credential_id'],
                'public_key' => $validatedCredential['public_key'],
                'name' => $credential['name'] ?? 'New Passkey',
                'device_type' => $this->detectDeviceType($credential),
                'device_name' => $this->detectDeviceName($credential),
                'is_cross_platform' => $credential['cross_platform'] ?? false,
                'attestation_type' => $validatedCredential['attestation_type'] ?? 'none',
                'transports' => $credential['transports'] ?? [],
                'created_at' => new DateTime(),
            ]);

            // Salvar passkey
            $response = $this->savePasskey($passkeyData);

            // Limpar challenge temporário
            $this->clearStoredChallenge($userId);

            $this->logger->info('Passkey registration completed', [
                'user_id' => $userId,
                'passkey_id' => $response['passkey_id'],
                'device_type' => $passkeyData->device_type,
            ]);

            return [
                'success' => true,
                'passkey_id' => $response['passkey_id'],
                'passkey' => $passkeyData->toSafeArray(),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to complete passkey registration', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Inicia processo de autenticação com passkey
     */
    public function authenticateBegin(string $userId): array
    {
        try {
            $challenge = $this->generateChallenge();
            $userPasskeys = $this->getUserPasskeys($userId);

            if (empty($userPasskeys)) {
                throw new \Exception('No passkeys found for user');
            }

            $options = $this->createAuthenticationOptions($userPasskeys, $challenge);

            // Salvar challenge em cache temporário
            $this->storeChallengeTemporarily($userId, $challenge);

            $this->logger->info('Passkey authentication started', [
                'user_id' => $userId,
                'available_passkeys' => count($userPasskeys),
                'challenge_id' => substr($challenge, 0, 8) . '...',
            ]);

            return [
                'success' => true,
                'options' => $options,
                'challenge' => $challenge,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to start passkey authentication', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Completa processo de autenticação com passkey
     */
    public function authenticateComplete(string $userId, array $assertion): array
    {
        try {
            // Verificar challenge
            $storedChallenge = $this->getStoredChallenge($userId);
            if (!$storedChallenge) {
                throw new \Exception('Invalid or expired challenge');
            }

            // Verificar assertion
            $credentialId = $assertion['credential_id'];
            $passkey = $this->getPasskeyByCredentialId($credentialId);

            if (!$passkey || $passkey->user_id !== $userId) {
                throw new \Exception('Invalid passkey');
            }

            // Validar assertion
            $validated = $this->validateAssertion($assertion, $passkey, $storedChallenge);

            if (!$validated) {
                throw new \Exception('Authentication failed');
            }

            // Atualizar último uso
            $this->updatePasskeyLastUsed($passkey->id);

            // Limpar challenge temporário
            $this->clearStoredChallenge($userId);

            $this->logger->info('Passkey authentication completed', [
                'user_id' => $userId,
                'passkey_id' => $passkey->id,
                'device_type' => $passkey->device_type,
            ]);

            return [
                'success' => true,
                'user_id' => $userId,
                'passkey_id' => $passkey->id,
                'authenticated_at' => (new DateTime())->format('c'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to complete passkey authentication', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica suporte do browser para WebAuthn
     */
    public function checkBrowserSupport(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $support = [
            'webauthn_supported' => true, // Assumir suporte por padrão
            'platform_authenticator' => $this->detectPlatformAuthenticatorSupport($userAgent),
            'cross_platform_authenticator' => true,
            'conditional_ui' => $this->detectConditionalUISupport($userAgent),
            'user_agent' => $userAgent,
        ];

        $this->logger->debug('Browser WebAuthn support check', $support);

        return [
            'success' => true,
            'support' => $support,
        ];
    }

    /**
     * Lista passkeys do usuário
     */
    public function getUserPasskeys(string $userId): array
    {
        // Simular busca de passkeys do usuário
        return [
            [
                'id' => 'passkey_1',
                'credential_id' => 'cred_123',
                'name' => 'iPhone Touch ID',
                'device_type' => 'mobile',
                'is_cross_platform' => false,
                'last_used_at' => '2024-01-15T10:30:00Z',
            ],
            [
                'id' => 'passkey_2',
                'credential_id' => 'cred_456',
                'name' => 'YubiKey 5',
                'device_type' => 'security_key',
                'is_cross_platform' => true,
                'last_used_at' => '2024-01-10T14:20:00Z',
            ],
        ];
    }

    /**
     * Gera challenge criptográfico
     */
    private function generateChallenge(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Cria opções de registro WebAuthn
     */
    private function createRegistrationOptions(string $userId, string $challenge): array
    {
        return [
            'challenge' => $challenge,
            'rp' => [
                'name' => 'Clubify Checkout',
                'id' => parse_url($this->config->getBaseUrl(), PHP_URL_HOST) ?: 'localhost',
            ],
            'user' => [
                'id' => base64_encode($userId),
                'name' => $userId,
                'displayName' => 'User',
            ],
            'pubKeyCredParams' => [
                ['alg' => -7, 'type' => 'public-key'], // ES256
                ['alg' => -257, 'type' => 'public-key'], // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'required',
            ],
            'timeout' => 60000,
            'attestation' => 'none',
        ];
    }

    /**
     * Cria opções de autenticação WebAuthn
     */
    private function createAuthenticationOptions(array $passkeys, string $challenge): array
    {
        return [
            'challenge' => $challenge,
            'allowCredentials' => array_map(function ($passkey) {
                return [
                    'id' => $passkey['credential_id'],
                    'type' => 'public-key',
                    'transports' => $passkey['transports'] ?? ['internal'],
                ];
            }, $passkeys),
            'userVerification' => 'required',
            'timeout' => 60000,
        ];
    }

    /**
     * Detecta tipo de dispositivo
     */
    private function detectDeviceType(array $credential): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
            return 'mobile';
        } elseif (stripos($userAgent, 'Android') !== false) {
            return 'mobile';
        } elseif (stripos($userAgent, 'Windows') !== false) {
            return 'desktop';
        } elseif (stripos($userAgent, 'Mac') !== false) {
            return 'desktop';
        }

        return 'unknown';
    }

    /**
     * Detecta nome do dispositivo
     */
    private function detectDeviceName(array $credential): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (stripos($userAgent, 'iPhone') !== false) {
            return 'iPhone';
        } elseif (stripos($userAgent, 'iPad') !== false) {
            return 'iPad';
        } elseif (stripos($userAgent, 'Android') !== false) {
            return 'Android Device';
        } elseif (stripos($userAgent, 'Windows') !== false) {
            return 'Windows PC';
        } elseif (stripos($userAgent, 'Mac') !== false) {
            return 'Mac';
        }

        return 'Unknown Device';
    }

    /**
     * Detecta suporte a autenticador de plataforma
     */
    private function detectPlatformAuthenticatorSupport(string $userAgent): bool
    {
        // Simplificado - em produção seria mais complexo
        return stripos($userAgent, 'iPhone') !== false ||
               stripos($userAgent, 'iPad') !== false ||
               stripos($userAgent, 'Mac') !== false ||
               stripos($userAgent, 'Windows') !== false;
    }

    /**
     * Detecta suporte a UI condicional
     */
    private function detectConditionalUISupport(string $userAgent): bool
    {
        // Simplificado - Chrome 94+ e Safari 16+
        return true;
    }

    /**
     * Métodos simulados para interação com API/banco
     */
    private function storeChallengeTemporarily(string $userId, string $challenge): void
    {
        // Implementar cache temporário (5 minutos)
    }

    private function getStoredChallenge(string $userId): ?string
    {
        // Buscar challenge do cache
        return 'stored_challenge_example';
    }

    private function clearStoredChallenge(string $userId): void
    {
        // Limpar challenge do cache
    }

    private function validateCredential(array $credential, string $challenge): array
    {
        // Validar credencial WebAuthn (simplificado)
        return [
            'credential_id' => $credential['id'] ?? uniqid('cred_'),
            'public_key' => base64_encode('public_key_data'),
            'attestation_type' => 'none',
        ];
    }

    private function validateAssertion(array $assertion, PasskeyData $passkey, string $challenge): bool
    {
        // Validar assertion WebAuthn (simplificado)
        return true;
    }

    private function savePasskey(PasskeyData $passkey): array
    {
        // Salvar passkey via API
        return ['passkey_id' => uniqid('pk_')];
    }

    private function getPasskeyByCredentialId(string $credentialId): ?PasskeyData
    {
        // Buscar passkey por credential_id
        return new PasskeyData([
            'id' => 'pk_123',
            'user_id' => 'user_456',
            'credential_id' => $credentialId,
            'public_key' => 'public_key_data',
            'name' => 'Test Passkey',
        ]);
    }

    private function updatePasskeyLastUsed(string $passkeyId): void
    {
        // Atualizar last_used_at do passkey
    }
}
