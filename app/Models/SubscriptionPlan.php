<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_key',
        'name',
        'guards_limit',
        'locations_limit',
        'checkpoints_limit',
        'price_monthly',
    ];

    protected $casts = [
        'guards_limit' => 'integer',
        'locations_limit' => 'integer',
        'checkpoints_limit' => 'integer',
        'price_monthly' => 'float',
    ];
}
