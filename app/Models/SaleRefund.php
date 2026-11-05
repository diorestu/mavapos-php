<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleRefund extends Model
{
    public const METHODS = ['cash' => 'Tunai', 'qris' => 'QRIS manual', 'card' => 'Kartu manual', 'bank_transfer' => 'Transfer bank manual'];

    protected $fillable = ['tenant_id', 'branch_id', 'pos_sale_id', 'user_id', 'cashier_shift_id', 'client_refund_id', 'request_hash', 'refund_number', 'amount', 'refund_method', 'reason', 'refunded_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'refunded_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleRefundItem::class);
    }
}
