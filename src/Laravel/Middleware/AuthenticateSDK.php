<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Middleware;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\AuthenticationException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware para autenticação do SDK
 */
final class AuthenticateSDK
{
    /**
     * SDK instance
     */
    private ClubifyCheckoutSDK $sdk;

    /**
     * Construtor
     */
    public function __construct(ClubifyCheckoutSDK $sdk)
    {
        $this->sdk = $sdk;
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next, string ...$permissions): SymfonyResponse
    {
        try {
            // Verifica se o SDK está inicializado
            if (!$this->sdk->isInitialized()) {
                throw new AuthenticationException('SDK não inicializado');
            }

            // Verifica autenticação básica
            $this->validateAuthentication($request);

            // Verifica permissões específicas se fornecidas
            if (!empty($permissions)) {
                $this->validatePermissions($permissions);
            }

            // Adiciona informações do SDK ao request
            $this->enrichRequest($request);

            return $next($request);

        } catch (AuthenticationException $e) {
            return $this->unauthorizedResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse('Erro de autenticação', $e->getMessage());
        }
    }

    /**
     * Valida autenticação
     */
    private function validateAuthentication(Request $request): void
    {
        // Verifica se há token válido
        $authManager = $this->sdk->getAuthManager();

        if (!$authManager->isAuthenticated()) {
            throw new AuthenticationException('Token de autenticação inválido ou expirado');
        }

        // Verifica se o token não está próximo do vencimento
        if ($authManager->isTokenNearExpiry()) {
            // Tenta renovar automaticamente
            try {
                $authManager->refreshToken();
            } catch (\Exception $e) {
                throw new AuthenticationException('Falha ao renovar token de autenticação');
            }
        }

        // Valida tenant se fornecido no request
        $this->validateTenant($request);
    }

    /**
     * Valida tenant
     */
    private function validateTenant(Request $request): void
    {
        $tenantId = $request->header('X-Tenant-ID') ?? $request->input('tenant_id');

        if ($tenantId) {
            $authManager = $this->sdk->getAuthManager();
            $currentTenant = $authManager->getCurrentTenant();

            if (!$currentTenant || $currentTenant['id'] !== $tenantId) {
                throw new AuthenticationException('Tenant ID inválido');
            }
        }
    }

    /**
     * Valida permissões específicas
     */
    private function validatePermissions(array $permissions): void
    {
        $authManager = $this->sdk->getAuthManager();

        foreach ($permissions as $permission) {
            if (!$authManager->hasPermission($permission)) {
                throw new AuthenticationException("Permissão insuficiente: {$permission}");
            }
        }
    }

    /**
     * Enriquece o request com informações do SDK
     */
    private function enrichRequest(Request $request): void
    {
        $authManager = $this->sdk->getAuthManager();

        // Adiciona informações do usuário
        $userInfo = $authManager->getUserInfo();
        if ($userInfo) {
            $request->attributes->set('clubify_user', $userInfo);
        }

        // Adiciona informações do tenant
        $tenant = $authManager->getCurrentTenant();
        if ($tenant) {
            $request->attributes->set('clubify_tenant', $tenant);
        }

        // Adiciona stats do SDK
        $request->attributes->set('clubify_stats', $this->sdk->getStats());
    }

    /**
     * Resposta de não autorizado
     */
    private function unauthorizedResponse(string $message): Response
    {
        $data = [
            'error' => 'Unauthorized',
            'message' => $message,
            'code' => 401,
            'timestamp' => now()->toISOString(),
        ];

        return response()->json($data, 401, [
            'WWW-Authenticate' => 'Bearer realm="Clubify Checkout SDK"',
        ]);
    }

    /**
     * Resposta de erro
     */
    private function errorResponse(string $error, string $message): Response
    {
        $data = [
            'error' => $error,
            'message' => $message,
            'code' => 500,
            'timestamp' => now()->toISOString(),
        ];

        return response()->json($data, 500);
    }
}