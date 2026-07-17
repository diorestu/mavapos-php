<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\TenantScoped;

class Product extends Model
{
    use TenantScoped;

    protected $fillable = [
        'user_id',
        'product_category_id',
        'sku',
        'name',
        'barcode',
        'image_path',
        'buy_price',
        'sell_price',
        'stock',
        'stock_mode',
        'min_stock',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'buy_price' => 'integer',
            'sell_price' => 'integer',
            'stock' => 'integer',
            'stock_mode' => 'string',
            'min_stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function branchInventories(): HasMany
    {
        return $this->hasMany(BranchInventory::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(ProductRecipeItem::class);
    }
}
