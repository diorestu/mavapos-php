<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\TenantScoped;

class ProductCategory extends Model
{
    use TenantScoped;

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'product_count',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'product_count' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
