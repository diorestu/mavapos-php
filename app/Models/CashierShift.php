<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = [
        'user_id',
        'opened_at',
        'closed_at',
        'sales_count',
        'gross_sales',
        'discount_total',
        'net_sales',
        'cash_total',
        'qris_total',
        'card_total',
        'opening_note',
        'closing_note',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'sales_count' => 'integer',
            'gross_sales' => 'integer',
            'discount_total' => 'integer',
            'net_sales' => 'integer',
            'cash_total' => 'integer',
            'qris_total' => 'integer',
            'card_total' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }
}
