<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscriptionLog extends Model
{
    use HasFactory;

    protected $table = 'tenant_subscription_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'changed_by',
        'previous_plan',
        'new_plan',
        'previous_until',
        'new_until',
    ];

    protected $casts = [
        'previous_until' => 'datetime',
        'new_until' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
