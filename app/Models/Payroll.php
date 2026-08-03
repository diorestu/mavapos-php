<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = ['user_id', 'branch_id', 'period_month', 'basic_salary', 'fixed_allowance', 'sales_count', 'sales_bonus', 'total_amount'];

    protected function casts(): array
    {
        return ['period_month' => 'date', 'basic_salary' => 'integer', 'fixed_allowance' => 'integer', 'sales_count' => 'integer', 'sales_bonus' => 'integer', 'total_amount' => 'integer'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
