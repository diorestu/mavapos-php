<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\TenantScoped;

class Branch extends Model
{
    use TenantScoped;

    protected $fillable = [
        'user_id',
        'name',
        'code',
        'phone',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'user_id' => 'integer',
        ];
    }

    public function cashierShifts(): HasMany
    {
        return $this->hasMany(CashierShift::class);
    }

    public function posSales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(BranchInventory::class);
    }
}
