<?php

namespace App\Models;

use App\Enums\PrintJobStatus;
use App\Enums\PrintJobType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrintJob extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'terminal_id',
        'type',
        'status',
        'content',
        'open_drawer',
        'reference_type',
        'reference_id',
        'attempts',
        'error_message',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => PrintJobType::class,
            'status' => PrintJobStatus::class,
            'open_drawer' => 'boolean',
            'printed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
