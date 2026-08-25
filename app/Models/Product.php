<?php

namespace App\Models;

use App\Enums\ProductUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'product_category_id',
        'sku',
        'barcode',
        'name',
        'description',
        'price',
        'cost_price',
        'tax_rate',
        'image_path',
        'unit',
        'is_inventoried',
        'is_sellable',
        'is_active',
        'min_stock',
        'max_stock',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'unit' => ProductUnit::class,
            'is_inventoried' => 'boolean',
            'is_sellable' => 'boolean',
            'is_active' => 'boolean',
            'min_stock' => 'decimal:3',
            'max_stock' => 'decimal:3',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_group');
    }
}
