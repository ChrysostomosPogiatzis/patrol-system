<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'enforce_order',
        'allow_skip',
        'expected_duration_mins',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'enforce_order' => 'boolean',
        'allow_skip' => 'boolean',
        'expected_duration_mins' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function routeCheckpoints(): HasMany
    {
        return $this->hasMany(RouteCheckpoint::class)->where('position', '>', 0)->orderBy('position');
    }

    public function checkpoints(): BelongsToMany
    {
        return $this->belongsToMany(Checkpoint::class, 'route_checkpoints')
                    ->wherePivot('position', '>', 0)
                    ->withPivot('id', 'position', 'photo_requirement', 'note_requirement', 'voice_requirement', 'signature_required')
                    ->withTimestamps()
                    ->orderBy('pivot_position');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RouteAssignment::class);
    }

    public function patrols(): HasMany
    {
        return $this->hasMany(Patrol::class);
    }
}
