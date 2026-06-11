<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatrolCheckpointLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'patrol_checkpoint_logs';

    protected $fillable = [
        'tenant_id',
        'patrol_id',
        'route_checkpoint_id',
        'checkpoint_id',
        'guard_id',
        'status', // pending, scanned, skipped, out_of_order_attempt
        'position',
        'scan_method_used', // qr, nfc
        'scanned_at',
        'scan_latitude',
        'scan_longitude',
        'gps_distance_metres',
        'gps_within_fence',
        'note',
        'skip_reason',
        'skipped_at',
        'attempted_position',
        'recorded_offline',
        'device_timestamp',
        'synced_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'scanned_at' => 'datetime',
        'scan_latitude' => 'decimal:8',
        'scan_longitude' => 'decimal:8',
        'gps_distance_metres' => 'decimal:2',
        'gps_within_fence' => 'boolean',
        'skipped_at' => 'datetime',
        'attempted_position' => 'integer',
        'recorded_offline' => 'boolean',
        'device_timestamp' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function patrol(): BelongsTo
    {
        return $this->belongsTo(Patrol::class);
    }

    public function routeCheckpoint(): BelongsTo
    {
        return $this->belongsTo(RouteCheckpoint::class);
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(CheckpointMedia::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
