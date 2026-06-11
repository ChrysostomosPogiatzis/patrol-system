<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSyncQueue extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'offline_sync_queue';

    public $timestamps = false; // queued_at maps custom tracking time

    protected $fillable = [
        'tenant_id',
        'guard_id',
        'patrol_id',
        'entity_type',
        'entity_id',
        'payload',
        'state', // queued, syncing, synced, failed
        'retry_count',
        'last_error',
        'captured_at',
        'queued_at',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'retry_count' => 'integer',
        'captured_at' => 'datetime',
        'queued_at' => 'datetime',
        'synced_at' => 'datetime',
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
