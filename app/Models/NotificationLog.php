<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'notification_log';

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'guard_id',
        'channel',
        'trigger',
        'entity_type',
        'entity_id',
        'recipient',
        'subject',
        'body',
        'sent',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'sent' => 'boolean',
        'sent_at' => 'datetime',
        'entity_id' => 'integer',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(PatrolContact::class, 'contact_id');
    }

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }
}
