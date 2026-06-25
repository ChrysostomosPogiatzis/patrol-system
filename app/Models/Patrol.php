<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patrol extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'route_id',
        'guard_id',
        'status', // pending, in_progress, completed, abandoned
        'scheduled_start',
        'started_at',
        'completed_at',
        'duration_seconds',
        'general_note',
        'completed_by_guard',
        'completion_latitude',
        'completion_longitude',
        'completion_signature_url',
        'was_offline',
        'synced_at',
        'total_checkpoints',
        'completed_checkpoints',
        'skipped_checkpoints',
        'incident_count',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
        'completed_by_guard' => 'boolean',
        'completion_latitude' => 'decimal:8',
        'completion_longitude' => 'decimal:8',
        'was_offline' => 'boolean',
        'synced_at' => 'datetime',
        'total_checkpoints' => 'integer',
        'completed_checkpoints' => 'integer',
        'skipped_checkpoints' => 'integer',
        'incident_count' => 'integer',
    ];
    
    protected $appends = ['checkpoint_logs'];

    public function getCheckpointLogsAttribute()
    {
        return $this->relationLoaded('checkpointLogs') ? $this->getRelation('checkpointLogs') : null;
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function checkpointLogs(): HasMany
    {
        return $this->hasMany(PatrolCheckpointLog::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(CheckpointMedia::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function sosAlerts(): HasMany
    {
        return $this->hasMany(SosAlert::class);
    }

    public function locationPings(): HasMany
    {
        return $this->hasMany(GuardLocationPing::class);
    }
}
