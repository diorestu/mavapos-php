<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\TenantScoped;

class RawMaterial extends Model
{
    use TenantScoped;

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'category',
        'unit',
        'stock',
        'min_stock',
        'cost_per_unit',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'cost_per_unit' => 'decimal:2',
        ];
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(ProductRecipeItem::class);
    }
}
