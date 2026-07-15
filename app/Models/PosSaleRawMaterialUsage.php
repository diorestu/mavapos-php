<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleRawMaterialUsage extends Model
{
    protected $fillable = ['pos_sale_id', 'raw_material_id', 'quantity', 'unit', 'is_legacy_fallback'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'is_legacy_fallback' => 'boolean'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
