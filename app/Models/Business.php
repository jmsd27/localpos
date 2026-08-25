<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'tax_id',
        'address',
        'phone',
        'email',
        'currency',
        'timezone',
        'logo_path',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }
}
