<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;

class ServiceProviderAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        Log::info('ServiceProviderAuth Middleware: Received Token: ' . ($token ? 'Yes' : 'No'));

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $secret = env('JWT_SECRET');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            Log::info('ServiceProviderAuth decoded JWT', ['decoded' => (array)$decoded]);

            // Ensure the user is a service provider (either by role, user_type, or user_type_id)
            $userData = $decoded->data ?? ($decoded->user ?? $decoded);
            
            // HYDRATE: Fetch fresh user data from the database to ensure we have the LATEST service_provider_id
            try {
                $sub = $decoded->sub ?? ($userData->id ?? null);
                if ($sub) {
                    $freshUser = app(\App\Services\UserService::class)->getUserById($sub);
                    if ($freshUser) {
                        // Merge fresh data into the userData object
                        foreach ($freshUser as $key => $value) {
                            $userData->{$key} = $value;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('ServiceProviderAuth: Failed to hydrate user data', ['error' => $e->getMessage()]);
            }

            // Map service_provider_id from token/fresh data, or fallback if absolutely necessary
            $userData->service_provider_id = $userData->service_provider_id ?? ($userData->company_id ?? null);

            $roles = (array) ($userData->roles ?? ($userData->role ?? []));
            $userType = $userData->user_type->type ?? ($userData->user_type ?? ($userData->type ?? ''));
            $userTypeId = $userData->user_type_id ?? ($userData->user_type->id ?? ($userData->type_id ?? ''));
            
            $isServiceProvider = in_array('service_provider', $roles) || 
                                ($userData->role ?? '') === 'service_provider' ||
                                $userType === 'service_provider' ||
                                (string)$userTypeId === '2';

            // If the user *is* the service provider and has no service_provider_id explicitly set, fallback to their own ID
            if ($isServiceProvider && empty($userData->service_provider_id)) {
                $userData->service_provider_id = $userData->id ?? null;
            }

            if (!$isServiceProvider) {
                 Log::warning('ServiceProviderAuth: Authorization failed for user', [
                     'userData' => (array)$userData,
                     'attempted_user_type' => $userType,
                     'attempted_user_type_id' => $userTypeId
                 ]);
                 return response()->json(['success' => false, 'message' => 'Forbidden: This action requires a service provider account.'], 403);
            }

            // Ensure the main decoded object has the property for easy access
            if (!isset($decoded->service_provider_id) && isset($userData->service_provider_id)) {
                $decoded->service_provider_id = $userData->service_provider_id;
            }

            // Store in request attributes
            $request->attributes->set('user', $userData);

            // Also set as the Laravel user to support auth()->user()
            auth()->setUser(new \Illuminate\Auth\GenericUser((array) $userData));

        } catch (\Exception $e) {
            Log::error('ServiceProviderAuth JWT Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
