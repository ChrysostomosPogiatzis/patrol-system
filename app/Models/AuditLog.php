<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'audit_log';

    protected $fillable = [
        'tenant_id',
        'actor_type',
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'entity_id' => 'integer',
        'old_value' => 'array',
        'new_value' => 'array',
    ];
}
