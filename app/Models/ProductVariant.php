<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'unit',
        'unit_conversion',
        'attributes',
        'buy_price',
        'sell_price',
        'stock',
        'min_stock',
        'is_active',
        'is_favorite',
        'is_taxable',
        'is_discountable',
        'serving_time_minutes',
        'available_for_dine_in',
        'available_for_takeaway',
    ];

    protected function casts(): array
    {
        return [
            'unit_conversion' => 'integer',
            'attributes' => 'array',
            'buy_price' => 'integer',
            'sell_price' => 'integer',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
            'is_favorite' => 'boolean',
            'is_taxable' => 'boolean',
            'is_discountable' => 'boolean',
            'serving_time_minutes' => 'integer',
            'available_for_dine_in' => 'boolean',
            'available_for_takeaway' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branchInventories(): HasMany
    {
        return $this->hasMany(BranchInventory::class);
    }
}
