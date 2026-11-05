<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tenant_owner_id' => 'integer',
            'actor_id' => 'integer',
            'cashier_id' => 'integer',
            'branch_id' => 'integer',
            'response_status' => 'integer',
        ];
    }
}
