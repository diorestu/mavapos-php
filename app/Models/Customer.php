<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

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
