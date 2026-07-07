<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'branch_id',
        'product_id',
        'stock_movement_id',
        'expense_number',
        'type',
        'category',
        'title',
        'amount',
        'quantity',
        'unit_cost',
        'reference',
        'note',
        'spent_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'branch_id' => 'integer',
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'spent_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function affectsStock(): bool
    {
        return $this->type === 'stock';
    }
}
