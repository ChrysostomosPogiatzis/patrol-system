<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
