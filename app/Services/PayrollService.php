<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Support\Carbon;

class PayrollService
{
    public function calculate(User $user, int $branchId, Carbon $month): array
    {
        $bonus = app(SalesBonusService::class)->forUserMonth($user->id, $branchId, $month);
        $basic = (int) $user->basic_salary;
        $allowance = (int) $user->fixed_allowance;
        return ['basic_salary' => $basic, 'fixed_allowance' => $allowance, 'sales_count' => $bonus['salesCount'], 'sales_bonus' => $bonus['bonus'], 'total_amount' => $basic + $allowance + $bonus['bonus']];
    }

    public function generate(Carbon $month): int
    {
        $branchId = app(BranchContext::class)->activeId();
        $users = User::query()->where('tenant_owner_id', auth()->user()->tenantOwnerId())->whereNotIn('role', ['owner', 'admin'])->get();
        foreach ($users as $user) {
            Payroll::query()->updateOrCreate(
                ['user_id' => $user->id, 'branch_id' => $branchId, 'period_month' => $month->copy()->startOfMonth()->toDateString()],
                ['user_id' => $user->id, 'branch_id' => $branchId, 'period_month' => $month->copy()->startOfMonth()->toDateString()] + $this->calculate($user, $branchId, $month)
            );
        }
        return $users->count();
    }
}
