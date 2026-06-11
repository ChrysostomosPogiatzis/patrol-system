<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SubscriptionPlan;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'phone',
        'email',
        'address',
        'logo_url',
        'timezone',
        'subscription_plan',
        'subscription_until',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'subscription_until' => 'datetime',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    protected $appends = [
        'plan_details',
    ];

    public function getPlanDetailsAttribute(): array
    {
        $planKey = $this->subscription_plan ?: 'basic';
        $plan = SubscriptionPlan::where('plan_key', $planKey)->first();
        if ($plan) {
            return [
                'name' => $plan->name,
                'guards_limit' => $plan->guards_limit,
                'locations_limit' => $plan->locations_limit,
                'checkpoints_limit' => $plan->checkpoints_limit,
                'price_monthly' => (float) $plan->price_monthly,
            ];
        }

        // Fallback
        return [
            'name' => 'Basic Plan',
            'guards_limit' => 2,
            'locations_limit' => 2,
            'checkpoints_limit' => 5,
            'price_monthly' => 29.00,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    public function guards(): HasMany
    {
        return $this->hasMany(Guard::class);
    }

    public function subscriptionLogs(): HasMany
    {
        return $this->hasMany(TenantSubscriptionLog::class)->orderBy('id', 'desc');
    }
}
