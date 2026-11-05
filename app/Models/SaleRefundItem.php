<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleRefundItem extends Model
{
    protected $fillable = ['sale_refund_id', 'pos_sale_item_id', 'quantity', 'amount', 'restock'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'amount' => 'integer', 'restock' => 'boolean'];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(SaleRefund::class, 'sale_refund_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class, 'pos_sale_item_id');
    }
}
