<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SosAlert extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sos_alerts';

    protected $fillable = [
        'tenant_id',
        'guard_id',
        'patrol_id',
        'status', // active, acknowledged, resolved, false_alarm
        'triggered_latitude',
        'triggered_longitude',
        'note',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
        'resolution_note',
        'triggered_at',
    ];

    protected $casts = [
        'triggered_latitude' => 'decimal:8',
        'triggered_longitude' => 'decimal:8',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'triggered_at' => 'datetime',
    ];

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function patrol(): BelongsTo
    {
        return $this->belongsTo(Patrol::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function locationPings(): HasMany
    {
        return $this->hasMany(SosLocationPing::class, 'sos_alert_id');
    }
}
