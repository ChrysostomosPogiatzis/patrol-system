<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosLocationPing extends Model
{
    use HasFactory;

    protected $table = 'sos_location_pings';

    public $timestamps = false; // Custom timestamp used for pinged_at

    protected $fillable = [
        'sos_alert_id',
        'guard_id',
        'latitude',
        'longitude',
        'accuracy_m',
        'pinged_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy_m' => 'decimal:2',
        'pinged_at' => 'datetime',
    ];

    public function sosAlert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class);
    }

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }
}
