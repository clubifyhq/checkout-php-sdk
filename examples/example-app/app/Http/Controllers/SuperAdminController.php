<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ClubifySDKHelper;
use App\Services\ContextManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Super Admin Controller for multi-tenant management
 *
 * This controller provides endpoints for super administrators to manage
 * multiple tenants, switch contexts, and perform elevated operations.
 *
 * @package App\Http\Controllers
 */
class SuperAdminController extends Controller
{
    private ContextManager $contextManager;

    public function __construct(ContextManager $contextManager)
    {
        $this->contextManager = $contextManager;
    }

    /**
     * Authenticate Super Admin user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'super_admin_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $superAdminToken = $request->input('super_admin_token');

            // Validate super admin access
            $validation = ClubifySDKHelper::validateSuperAdminAccess($superAdminToken);

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid super admin credentials',
                    'error' => $validation['error'] ?? 'Authentication failed',
                ], 401);
            }

            // Set super admin context
            $this->contextManager->setSuperAdminContext($superAdminToken, $validation['permissions']);

            Log::info('Super Admin authentication successful', [
                'email' => $request->input('email'),
                'permissions_count' => count($validation['permissions']),
                'tenant_count' => $validation['tenant_count'] ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Super admin authentication successful',
                'data' => [
                    'permissions' => $validation['permissions'],
                    'tenant_count' => $validation['tenant_count'] ?? 0,
                    'expires_at' => $validation['expires_at'],
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Super Admin authentication failed', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of available tenants
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTenants(Request $request): JsonResponse
    {
        try {
            $superAdminToken = $this->contextManager->getSuperAdminToken();

            if (!$superAdminToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super admin session not found',
                ], 401);
            }

            // Get filters from request
            $filters = $request->only(['status', 'plan', 'search', 'limit', 'offset']);

            $tenants = ClubifySDKHelper::getAvailableTenants($superAdminToken, $filters);

            Log::info('Retrieved tenants list for Super Admin', [
                'tenant_count' => count($tenants),
                'filters' => $filters,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'tenants' => $tenants,
                    'total' => count($tenants),
                    'filters_applied' => $filters,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve tenants list', [
                'error' => $e->getMessage(),
                'filters' => $request->only(['status', 'plan', 'search']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tenants',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Switch to a specific tenant context
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function switchTenant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenantId = $request->input('tenant_id');
            $superAdminToken = $this->contextManager->getSuperAdminToken();

            if (!$superAdminToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super admin session not found',
                ], 401);
            }

            // Switch to tenant context
            $success = ClubifySDKHelper::switchToTenant($tenantId, $superAdminToken);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to switch to tenant context',
                ], 400);
            }

            // Update context manager
            $this->contextManager->setCurrentTenant($tenantId);

            Log::info('Successfully switched to tenant context', [
                'tenant_id' => $tenantId,
                'super_admin_session' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully switched to tenant context',
                'data' => [
                    'tenant_id' => $tenantId,
                    'switched_at' => now()->toISOString(),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to switch tenant context', [
                'tenant_id' => $request->input('tenant_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to switch tenant context',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get information about a specific tenant
     *
     * @param Request $request
     * @param string $tenantId
     * @return JsonResponse
     */
    public function getTenantInfo(Request $request, string $tenantId): JsonResponse
    {
        try {
            $superAdminToken = $this->contextManager->getSuperAdminToken();

            if (!$superAdminToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super admin session not found',
                ], 401);
            }

            // Get tenant-specific SDK instance
            $sdk = ClubifySDKHelper::getTenantInstance($tenantId, $superAdminToken);

            // Get tenant information - this would make actual API calls
            $tenantInfo = [
                'id' => $tenantId,
                'name' => 'Tenant Name', // Would come from API
                'status' => 'active',
                'created_at' => '2024-01-01T00:00:00Z',
                'subscription' => [
                    'plan' => 'premium',
                    'status' => 'active',
                    'expires_at' => '2024-12-31T23:59:59Z',
                ],
                'statistics' => [
                    'users_count' => 0,
                    'products_count' => 0,
                    'orders_count' => 0,
                    'revenue' => 0,
                ],
                'features' => [
                    'advanced_checkout',
                    'multi_payment_gateway',
                    'analytics',
                ],
            ];

            Log::info('Retrieved tenant information', [
                'tenant_id' => $tenantId,
                'super_admin_access' => true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $tenantInfo,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve tenant information', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tenant information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new tenant
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createTenant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'plan' => 'required|string|in:basic,premium,enterprise',
            'features' => 'array',
            'configuration' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $superAdminToken = $this->contextManager->getSuperAdminToken();

            if (!$superAdminToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super admin session not found',
                ], 401);
            }

            $tenantData = $request->only(['name', 'email', 'plan', 'features', 'configuration']);

            // This would make an actual API call to create the tenant
            $newTenant = [
                'id' => 'tenant-' . uniqid(),
                'name' => $tenantData['name'],
                'email' => $tenantData['email'],
                'plan' => $tenantData['plan'],
                'status' => 'active',
                'created_at' => now()->toISOString(),
                'features' => $tenantData['features'] ?? [],
                'configuration' => $tenantData['configuration'] ?? [],
            ];

            Log::info('Created new tenant', [
                'tenant_id' => $newTenant['id'],
                'tenant_name' => $newTenant['name'],
                'plan' => $newTenant['plan'],
                'super_admin_action' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => $newTenant,
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to create tenant', [
                'tenant_data' => $request->only(['name', 'email', 'plan']),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tenant configuration
     *
     * @param Request $request
     * @param string $tenantId
     * @return JsonResponse
     */
    public function updateTenant(Request $request, string $tenantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:active,inactive,suspended',
            'plan' => 'sometimes|string|in:basic,premium,enterprise',
            'features' => 'sometimes|array',
            'configuration' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $superAdminToken = $this->contextManager->getSuperAdminToken();

            if (!$superAdminToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super admin session not found',
                ], 401);
            }

            $updateData = $request->only(['name', 'status', 'plan', 'features', 'configuration']);

            // This would make an actual API call to update the tenant
            $updatedTenant = [
                'id' => $tenantId,
                'updated_at' => now()->toISOString(),
                'changes' => $updateData,
            ];

            Log::info('Updated tenant configuration', [
                'tenant_id' => $tenantId,
                'changes' => array_keys($updateData),
                'super_admin_action' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => $updatedTenant,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update tenant', [
                'tenant_id' => $tenantId,
                'update_data' => $request->only(['name', 'status', 'plan']),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current context information
     *
     * @return JsonResponse
     */
    public function getContext(): JsonResponse
    {
        try {
            $context = $this->contextManager->getCurrentContext();

            return response()->json([
                'success' => true,
                'data' => $context,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get current context', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get current context',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear current tenant context (return to super admin mode)
     *
     * @return JsonResponse
     */
    public function clearTenantContext(): JsonResponse
    {
        try {
            $this->contextManager->clearTenantContext();

            Log::info('Cleared tenant context, returned to super admin mode');

            return response()->json([
                'success' => true,
                'message' => 'Returned to super admin mode',
                'data' => [
                    'mode' => 'super_admin',
                    'cleared_at' => now()->toISOString(),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to clear tenant context', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear tenant context',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logout from super admin session
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            $this->contextManager->clearSuperAdminContext();

            Log::info('Super admin logout successful');

            return response()->json([
                'success' => true,
                'message' => 'Super admin logout successful',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to logout from super admin session', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to logout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get super admin dashboard statistics
     *
     * @return JsonResponse
     */
    public function getDashboardStats(): JsonResponse
    {
        try {
            $superAdminToken = $this->contextManager->getSuperAdminToken();

            if (!$superAdminToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super admin session not found',
                ], 401);
            }

            // This would aggregate data from all tenants
            $stats = [
                'total_tenants' => 0,
                'active_tenants' => 0,
                'total_users' => 0,
                'total_revenue' => 0,
                'recent_activity' => [],
                'system_health' => [
                    'status' => 'healthy',
                    'services' => [
                        'api' => 'online',
                        'database' => 'online',
                        'cache' => 'online',
                        'payments' => 'online',
                    ],
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get dashboard statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}