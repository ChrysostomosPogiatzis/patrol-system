<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $superadminTenants = [];
        $overrideTenantId = null;

        if ($user && $user->role === 'superadmin') {
            $superadminTenants = \App\Models\Tenant::select('id', 'name')->orderBy('name')->get();
            $overrideTenantId = session()->get('override_tenant_id');
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'superadmin_tenants' => $superadminTenants,
                'override_tenant_id' => $overrideTenantId,
            ],
        ];
    }
}
