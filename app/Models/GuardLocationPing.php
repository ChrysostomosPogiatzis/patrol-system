<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardLocationPing extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'guard_location_pings';

    public $timestamps = false; // pinged_at maps custom tracking time

    protected $fillable = [
        'tenant_id',
        'guard_id',
        'patrol_id',
        'latitude',
        'longitude',
        'accuracy_m',
        'battery_pct',
        'is_online',
        'recorded_offline',
        'device_timestamp',
        'pinged_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy_m' => 'decimal:2',
        'battery_pct' => 'integer',
        'is_online' => 'boolean',
        'recorded_offline' => 'boolean',
        'device_timestamp' => 'datetime',
        'pinged_at' => 'datetime',
    ];

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function patrol(): BelongsTo
    {
        return $this->belongsTo(Patrol::class);
    }
}
