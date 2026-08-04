<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\SalesBonusService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Carbon;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        $branchId = app(BranchContext::class)->activeId();
        $payrolls = Payroll::query()->with('user')->where('branch_id', $branchId)->whereDate('period_month', $month)->orderByDesc('total_amount')->get();
        $users = User::query()->where('tenant_owner_id', $request->user()->tenantOwnerId())->where('role', 'kasir')->orderBy('name')->get();
        $tab = $request->input('tab', 'payslip');
        $bonusTable = $tab === 'bonus'
            ? app(SalesBonusService::class)->monthlyPersonalBonus($branchId, $month, $users)
            : ['rows' => [], 'totals' => collect()];
        return view('pages.payrolls.index', compact('month', 'payrolls', 'users', 'tab', 'bonusTable'));
    }

    public function generate(Request $request, PayrollService $service): RedirectResponse
    {
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        $count = $service->generate($month);
        return back()->with('status', $count.' payslip berhasil dibuat/diperbarui.');
    }

    public function syncBonus(Request $request, PayrollService $service): RedirectResponse
    {
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        $count = $service->syncDailyBonus($month);
        return back()->with('status', 'Bonus harian '.$count.' staff berhasil disinkronkan ke payslip.');
    }

    public function salary(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'kasir', 422, 'Gaji hanya dapat diatur untuk staff kasir.');
        $data = $request->validate(['basic_salary' => ['required', 'integer', 'min:0']]);
        $user->update(['basic_salary' => $data['basic_salary'], 'fixed_allowance' => 0]);
        return back()->with('status', 'Gaji pokok '.$user->name.' berhasil disimpan.');
    }
}
