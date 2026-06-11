<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatrolContact extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'patrol_contacts';

    protected $fillable = [
        'tenant_id',
        'name',
        'role_label',
        'phone',
        'email',
        'notify_channels',
        'notify_on',
        'route_ids',
        'is_active',
    ];

    protected $casts = [
        'notify_channels' => 'array',
        'notify_on' => 'array',
        'route_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'contact_id');
    }
}
