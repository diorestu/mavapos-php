<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_number',
        'from_branch_id',
        'to_branch_id',
        'product_id',
        'user_id',
        'quantity',
        'from_stock_before',
        'from_stock_after',
        'to_stock_before',
        'to_stock_after',
        'note',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'from_branch_id' => 'integer',
            'to_branch_id' => 'integer',
            'product_id' => 'integer',
            'user_id' => 'integer',
            'quantity' => 'integer',
            'from_stock_before' => 'integer',
            'from_stock_after' => 'integer',
            'to_stock_before' => 'integer',
            'to_stock_after' => 'integer',
            'transferred_at' => 'datetime',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
