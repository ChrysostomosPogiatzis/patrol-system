<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Flag to prevent infinite recursion loop during authentication database lookups.
     *
     * @var bool
     */
    protected static bool $resolving = false;

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($model instanceof \App\Models\User && static::isSuperAdmin()) {
            return;
        }

        if ($tenantId = static::getTenantId()) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }

    /**
     * Check if the authenticated user is a superadmin.
     */
    public static function isSuperAdmin(): bool
    {
        if (static::$resolving) {
            return false;
        }

        static::$resolving = true;

        try {
            if (Auth::check()) {
                return Auth::user()->role === 'superadmin';
            }
        } catch (\Throwable $e) {
        } finally {
            static::$resolving = false;
        }

        return false;
    }

    /**
     * Get the active tenant ID from the current context.
     */
    public static function getTenantId(): ?int
    {
        if (static::$resolving) {
            return null;
        }

        if (app()->bound('current_tenant_id')) {
            return app('current_tenant_id');
        }

        static::$resolving = true;

        try {
            if (Auth::check()) {
                // Checks web user or any active default authenticated session
                $user = Auth::user();
                
                if ($user->role === 'superadmin') {
                    try {
                        if (function_exists('session') && session() && session()->has('override_tenant_id')) {
                            return (int) session()->get('override_tenant_id');
                        }
                    } catch (\Throwable $e) {
                        // Ignore session availability issues in CLI / migrations
                    }
                    return null;
                }
                
                return isset($user->tenant_id) ? (int) $user->tenant_id : null;
            }
        } finally {
            static::$resolving = false;
        }

        return null;
    }
}
