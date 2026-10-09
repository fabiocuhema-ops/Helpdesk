<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['demand_id', 'reviewed_by', 'model', 'status', 'input_digest', 'suggestion', 'error', 'reviewed_at'])]
class AiSuggestion extends Model
{
    protected function casts(): array
    {
        return [
            'input_digest' => 'array',
            'suggestion' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
