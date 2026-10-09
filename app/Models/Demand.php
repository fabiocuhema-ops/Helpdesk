<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'title',
    'description',
    'sector_id',
    'location_id',
    'task_type_id',
    'requester_id',
    'attendant_id',
    'manager_id',
    'executor_id',
    'team_id',
    'status',
    'priority',
    'complexity',
    'origin_system',
    'origin_reference',
    'lock_version',
    'started_at',
    'executor_delivered_at',
    'resolved_at',
])]
class Demand extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'executor_delivered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DemandEvent::class);
    }
}
