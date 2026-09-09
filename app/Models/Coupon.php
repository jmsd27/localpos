<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cupón / cortesía: un descuento con nombre y código que el cajero aplica al
 * cobrar en vez de teclear un descuento manual. Puede tener vigencia y tope
 * de usos. El descuento en sí se calcula igual que el manual (ver
 * SaleService::calculateDiscount) — el cupón solo aporta el tipo y el valor
 * y lleva la cuenta de cuántas veces se usó.
 */
class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'discount_type',
        'discount_value',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * ¿Se puede canjear hoy? Activo, dentro de vigencia y con usos disponibles.
     */
    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->valid_from && $today->lt($this->valid_from->startOfDay())) {
            return false;
        }

        if ($this->valid_until && $today->gt($this->valid_until->startOfDay())) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Motivo por el que no se puede canjear (para mostrarle al cajero), o null
     * si sí se puede.
     */
    public function redeemBlockReason(): ?string
    {
        if (! $this->is_active) {
            return 'La cortesía está desactivada.';
        }

        $today = now()->startOfDay();

        if ($this->valid_from && $today->lt($this->valid_from->startOfDay())) {
            return 'La cortesía todavía no está vigente (empieza el '.$this->valid_from->format('d/m/Y').').';
        }

        if ($this->valid_until && $today->gt($this->valid_until->startOfDay())) {
            return 'La cortesía venció el '.$this->valid_until->format('d/m/Y').'.';
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return 'La cortesía ya alcanzó su tope de usos.';
        }

        return null;
    }
}
