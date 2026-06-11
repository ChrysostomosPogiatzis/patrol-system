<?php
# app/Http/Middleware/CheckSubscription.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If not logged in, or superadmin, bypass check
        if (!$user || ($user instanceof \App\Models\User && $user->role === 'superadmin')) {
            return $next($request);
        }

        // Get tenant (compatible with both User and Guard models)
        $tenantId = $user->tenant_id;
        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant) {
                $isExpired = $tenant->subscription_until && $tenant->subscription_until->isPast();
                $isInactive = !$tenant->is_active;

                if ($isInactive || $isExpired) {
                    // Check if it expects JSON or is an API request (e.g. guard app / inertia api calls)
                    if ($request->expectsJson() || $request->is('api/*')) {
                        // Allow access to subscription, profile, and auth routes
                        $allowedUrls = [
                            'admin/api/subscription',
                            'profile',
                            'logout',
                        ];
                        
                        $isAllowed = false;
                        foreach ($allowedUrls as $url) {
                            if ($request->is($url) || $request->is($url . '/*') || $request->is('admin/api/' . $url) || $request->is('admin/api/' . $url . '/*')) {
                                $isAllowed = true;
                                break;
                            }
                        }

                        if ($isAllowed) {
                            return $next($request);
                        }

                        return response()->json([
                            'message' => 'Your company subscription has expired or is inactive. Access restricted.',
                            'subscription_expired' => true,
                        ], 403);
                    }

                    // Web Inertia requests
                    $allowedRoutes = ['admin.subscription', 'profile.edit', 'profile.update', 'profile.destroy', 'logout'];
                    $currentRoute = $request->route() ? $request->route()->getName() : null;

                    if ($currentRoute && in_array($currentRoute, $allowedRoutes)) {
                        return $next($request);
                    }

                    return redirect()->route('admin.subscription')->with('error', 'Your company subscription has expired or is inactive. Please renew.');
                }
            }
        }

        return $next($request);
    }
}
