<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'endpoint', 'keys', 'expired_at'])]
class PushSubscription extends Model
{
    protected function casts(): array
    {
        return [
            'keys' => 'array',
            'expired_at' => 'datetime',
        ];
    }
}
