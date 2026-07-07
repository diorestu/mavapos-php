<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchInventory extends Model
{
    protected $fillable = [
        'branch_id',
        'product_id',
        'product_variant_id',
        'stock',
        'min_stock',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'stock' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
