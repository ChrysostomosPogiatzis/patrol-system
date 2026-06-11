<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checkpoint extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'name',
        'description',
        'scan_method', // qr, nfc, both
        'qr_code',
        'nfc_tag_id',
        'gps_required',
        'gps_fence_radius',
        'latitude',
        'longitude',
        'photo_requirement', // off, optional, required
        'note_requirement',
        'voice_requirement',
        'signature_required',
        'incident_enabled',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'gps_required' => 'boolean',
        'gps_fence_radius' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'signature_required' => 'boolean',
        'incident_enabled' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function routeCheckpoints(): HasMany
    {
        return $this->hasMany(RouteCheckpoint::class);
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_checkpoints')
                    ->withPivot('position', 'photo_requirement', 'note_requirement', 'voice_requirement', 'signature_required')
                    ->withTimestamps();
    }
}
