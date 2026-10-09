<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['demand_id', 'user_id', 'team_id', 'rule_version', 'points', 'source_event_uuid', 'justification', 'reversal'])]
class ScoreLedger extends Model
{
    protected function casts(): array
    {
        return ['reversal' => 'boolean'];
    }
}
