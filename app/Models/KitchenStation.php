<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'branch_id',
        'name',
        'code',
        'color',
        'printer_terminal_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function printerTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'printer_terminal_id');
    }
}
