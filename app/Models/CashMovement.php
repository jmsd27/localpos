<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cash_register_session_id',
        'user_id',
        'order_id',
        'type',
        'payment_method',
        'amount',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
