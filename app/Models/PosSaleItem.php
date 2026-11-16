<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSaleItem extends Model
{
    protected $fillable = [
        'pos_sale_id',
        'product_id',
        'product_variant_id',
        'item_type',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'line_total',
        'variant_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'variant_snapshot' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosSaleItem $item): void {
            if ($item->product_variant_id && $item->variant_snapshot === null) {
                $variant = ProductVariant::find($item->product_variant_id);
                $item->variant_snapshot = $variant ? ['id' => $variant->id, 'name' => $variant->name, 'attributes' => $variant->getAttribute('attributes')] : null;
            }
        });
    }

    public function refundItems(): HasMany
    {
        return $this->hasMany(SaleRefundItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
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
