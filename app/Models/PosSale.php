<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    protected $fillable = [
        'cashier_shift_id',
        'branch_id',
        'user_id',
        'customer_id',
        'buyer_nationality',
        'invoice_number',
        'payment_method',
        'complimentary_category',
        'complimentary_recipient_name',
        'subtotal',
        'discount',
        'total',
        'paid_amount',
        'change_amount',
        'sold_at',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'branch_id' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'change_amount' => 'integer',
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
            'voided_by_user_id' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function rawMaterialUsages(): HasMany
    {
        return $this->hasMany(PosSaleRawMaterialUsage::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
