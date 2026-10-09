<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_uuid', 'demand_id', 'author_id', 'type', 'from_status', 'to_status', 'description', 'visible_to_requester', 'payload', 'occurred_at'])]
class DemandEvent extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'visible_to_requester' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
