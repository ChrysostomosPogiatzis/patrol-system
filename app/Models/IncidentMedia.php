<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentMedia extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'incident_media';

    protected $fillable = [
        'tenant_id',
        'incident_id',
        'guard_id',
        'kind', // photo, voice_memo
        'file_url',
        'file_key',
        'file_size_bytes',
        'mime_type',
        'duration_seconds',
        'captured_at',
        'capture_latitude',
        'capture_longitude',
        'recorded_offline',
        'synced_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'duration_seconds' => 'integer',
        'captured_at' => 'datetime',
        'capture_latitude' => 'decimal:8',
        'capture_longitude' => 'decimal:8',
        'recorded_offline' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }
}
