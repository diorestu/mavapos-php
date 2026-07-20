<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = [
        'user_id',
        'companion_staff_ids',
        'branch_id',
        'previous_cashier_shift_id',
        'opened_at',
        'closed_at',
        'sales_count',
        'gross_sales',
        'discount_total',
        'net_sales',
        'cash_total',
        'qris_total',
        'card_total',
        'opening_cash_amount',
        'validated_cash_amount',
        'validated_card_amount',
        'handover_validated_at',
        'opening_note',
        'opening_checklist',
        'closing_note',
        'closing_checklist',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'handover_validated_at' => 'datetime',
            'branch_id' => 'integer',
            'previous_cashier_shift_id' => 'integer',
            'sales_count' => 'integer',
            'gross_sales' => 'integer',
            'discount_total' => 'integer',
            'net_sales' => 'integer',
            'cash_total' => 'integer',
            'qris_total' => 'integer',
            'card_total' => 'integer',
            'opening_cash_amount' => 'integer',
            'validated_cash_amount' => 'integer',
            'validated_card_amount' => 'integer',
            'companion_staff_ids' => 'array',
            'opening_checklist' => 'array',
            'closing_checklist' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    public function previousShift(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_cashier_shift_id');
    }
}
