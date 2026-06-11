<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'patrol_id',
        'patrol_checkpoint_log_id',
        'checkpoint_id',
        'location_id',
        'guard_id',
        'title',
        'description',
        'priority', // low, medium, high, critical
        'status', // open, under_review, resolved, closed
        'incident_latitude',
        'incident_longitude',
        'resolved_by',
        'resolved_at',
        'resolution_note',
        'recorded_offline',
        'device_timestamp',
        'synced_at',
    ];

    protected $casts = [
        'incident_latitude' => 'decimal:8',
        'incident_longitude' => 'decimal:8',
        'resolved_at' => 'datetime',
        'recorded_offline' => 'boolean',
        'device_timestamp' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function patrol(): BelongsTo
    {
        return $this->belongsTo(Patrol::class);
    }

    public function checkpointLog(): BelongsTo
    {
        return $this->belongsTo(PatrolCheckpointLog::class, 'patrol_checkpoint_log_id');
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(IncidentMedia::class);
    }
}
