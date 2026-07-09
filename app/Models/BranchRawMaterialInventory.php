<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchRawMaterialInventory extends Model
{
    protected $fillable = [
        'branch_id',
        'raw_material_id',
        'stock',
        'min_stock',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'raw_material_id' => 'integer',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
