<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use TenantScoped;

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'phone',
        'email',
        'status',
        'loyalty_cup_balance',
        'loyalty_stamp_count',
        'loyalty_fifty_reward_available',
        'loyalty_free_reward_available',
        'address',
    ];

    public function freeCupClaims(): HasMany
    {
        return $this->hasMany(PosSale::class)->active()->whereIn('loyalty_reward', ['free_cup', 'physical_tenth']);
    }

    protected function casts(): array
    {
        return [
            'loyalty_cup_balance' => 'integer',
            'loyalty_stamp_count' => 'integer',
            'loyalty_fifty_reward_available' => 'boolean',
            'loyalty_free_reward_available' => 'boolean',
        ];
    }
}
