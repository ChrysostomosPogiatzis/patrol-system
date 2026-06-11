<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Guard extends Authenticatable
{
    use HasFactory, HasApiTokens, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'full_name',
        'phone',
        'employee_id',
        'avatar_url',
        'pin',
        'is_active',
        'last_login_at',
        'last_seen_at',
    ];

    protected $hidden = [
        'pin',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function otpTokens(): HasMany
    {
        return $this->hasMany(GuardOtpToken::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RouteAssignment::class);
    }

    public function patrols(): HasMany
    {
        return $this->hasMany(Patrol::class);
    }

    public function locationPings(): HasMany
    {
        return $this->hasMany(GuardLocationPing::class);
    }
}
