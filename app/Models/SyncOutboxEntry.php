<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SyncOutboxEntry extends Model
{
    public $timestamps = false;

    protected $table = 'sync_outbox';

    protected $fillable = [
        'business_id',
        'branch_id',
        'model_type',
        'model_id',
        'operation',
        'payload',
        'occurred_at',
        'synced_at',
        'attempts',
        'last_error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'synced_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('synced_at');
    }
}
