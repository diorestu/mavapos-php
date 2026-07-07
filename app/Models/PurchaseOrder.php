<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'branch_id',
        'supplier_id',
        'product_id',
        'user_id',
        'received_by',
        'expense_id',
        'stock_movement_id',
        'po_number',
        'status',
        'quantity',
        'unit_cost',
        'total_amount',
        'reference',
        'note',
        'ordered_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'supplier_id' => 'integer',
            'product_id' => 'integer',
            'user_id' => 'integer',
            'received_by' => 'integer',
            'expense_id' => 'integer',
            'stock_movement_id' => 'integer',
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'total_amount' => 'integer',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
