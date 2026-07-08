<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\TenantScoped;

class Supplier extends Model
{
    use TenantScoped;

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'phone',
        'email',
        'status',
        'address',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
