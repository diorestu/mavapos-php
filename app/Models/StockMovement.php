<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'branch_id',
        'created_by_user_id',
        'product_id',
        'product_variant_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'reference',
        'note',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'branch_id' => 'integer',
            'created_by_user_id' => 'integer',
            'product_variant_id' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
