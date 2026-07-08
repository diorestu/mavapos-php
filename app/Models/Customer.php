<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\TenantScoped;

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
        'address',
    ];
}
