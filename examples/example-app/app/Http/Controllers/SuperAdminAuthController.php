<?php

namespace App\Http\Controllers;

use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\AuthenticationException as SDKAuthException;
use Illuminate\Support\Facades\Log;

class SuperAdminAuthController extends Controller
{
    private ClubifyCheckoutSDK $sdk;

    public function __construct(ClubifyCheckoutSDK $sdk)
    {
        $this->sdk = $sdk;
    }
    /**
     * Show the super admin login form.
     */
    public function showLoginForm()
    {
        return view('clubify.super-admin.login');
    }

    /**
     * Handle super admin login.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $superAdmin = SuperAdmin::where('email', $request->email)
            ->where('status', 'active')
            ->first();

        if (!$superAdmin || !Hash::check($request->password, $superAdmin->password)) {
            return back()->withErrors([
                'email' => 'Credenciais inválidas.',
            ])->withInput($request->only('email'));
        }

        // Authenticate via SDK
        try {
            $this->sdk->getAuthManager()->authenticateAsSuperAdmin([
                'api_key' => config('clubify-checkout.super_admin.api_key'),
                'username' => $superAdmin->email,
                'user_id' => $superAdmin->id
            ]);

            // Store minimal session data
            session([
                'super_admin_authenticated' => true,
                'super_admin_context' => 'super_admin'
            ]);
        } catch (SDKAuthException $e) {
            Log::error('Super admin SDK authentication failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'email' => 'Falha na autenticação do sistema.'
            ])->withInput($request->only('email'));
        }

        // Update last login
        $superAdmin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return redirect()->route('super-admin.dashboard')
            ->with('success', 'Login realizado com sucesso!');
    }

    /**
     * Handle super admin logout.
     */
    public function logout(Request $request)
    {
        // Clear SDK authentication context
        try {
            $this->sdk->getCredentialManager()->removeContext('super_admin');
        } catch (\Exception $e) {
            Log::warning('Failed to clear SDK context during logout', [
                'error' => $e->getMessage()
            ]);
        }

        // Clear minimal session data
        session()->forget([
            'super_admin_authenticated',
            'super_admin_context'
        ]);

        return redirect()->route('super-admin.login')
            ->with('success', 'Logout realizado com sucesso!');
    }

    /**
     * API Login for super admin.
     */
    public function apiLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $superAdmin = SuperAdmin::where('email', $request->email)
            ->where('status', 'active')
            ->first();

        if (!$superAdmin || !Hash::check($request->password, $superAdmin->password)) {
            return response()->json([
                'error' => 'Credenciais inválidas',
                'message' => 'Email ou senha incorretos'
            ], 401);
        }

        // Create API token
        $token = $superAdmin->createToken('Super Admin API Token', ['super-admin:*']);

        // Update last login
        $superAdmin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login realizado com sucesso',
            'data' => [
                'super_admin' => [
                    'id' => $superAdmin->id,
                    'name' => $superAdmin->name,
                    'email' => $superAdmin->email,
                    'permissions' => $superAdmin->permissions,
                ],
                'token' => $token->plainTextToken,
                'expires_in' => config('super-admin.jwt.ttl', 3600),
            ]
        ]);
    }

    /**
     * API Logout for super admin.
     */
    public function apiLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso'
        ]);
    }

    /**
     * Get current super admin info.
     */
    public function me(Request $request)
    {
        $superAdmin = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $superAdmin->id,
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'permissions' => $superAdmin->permissions,
                'last_login_at' => $superAdmin->last_login_at,
                'created_at' => $superAdmin->created_at,
            ]
        ]);
    }
}