<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteCheckpoint extends Model
{
    use HasFactory;

    protected $table = 'route_checkpoints';

    protected $fillable = [
        'route_id',
        'checkpoint_id',
        'position',
        'photo_requirement',
        'note_requirement',
        'voice_requirement',
        'signature_required',
    ];

    protected $casts = [
        'position' => 'integer',
        'signature_required' => 'boolean',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }
}
