<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_uuid', 'demand_id', 'connector', 'direction', 'status', 'payload', 'attempts', 'next_attempt_at', 'last_error'])]
class OutboxEvent extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_attempt_at' => 'datetime',
        ];
    }
}
