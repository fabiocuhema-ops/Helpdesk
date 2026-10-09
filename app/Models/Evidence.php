<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['demand_id', 'uploaded_by', 'original_name', 'path', 'mime_type', 'size', 'visible_to_requester'])]
class Evidence extends Model
{
    protected $table = 'evidences';

    protected function casts(): array
    {
        return [
            'visible_to_requester' => 'boolean',
        ];
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
