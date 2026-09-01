<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'code',
        'address',
        'phone',
        'is_main',
        'sync_token',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
