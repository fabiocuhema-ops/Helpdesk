<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['external_id', 'title', 'description', 'requester_name', 'requester_email', 'sector_hint', 'status', 'fail_next_sync', 'received_events'])]
class LegacyTicket extends Model
{
    protected function casts(): array
    {
        return [
            'fail_next_sync' => 'boolean',
            'received_events' => 'array',
        ];
    }
}
