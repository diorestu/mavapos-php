<?php

use App\Models\Payroll;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

function missingPayrollUserFixture($test): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $test->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $staff = User::factory()->create(['role' => 'kasir', 'name' => 'Staf Riwayat', 'tenant_owner_id' => $owner->id, 'branch_id' => $branch->id]);
    $payroll = Payroll::create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'period_month' => '2026-11-01', 'basic_salary' => 2000000, 'sales_bonus' => 50000, 'total_amount' => 2050000]);

    return [$staff, $payroll];
}

test('payroll history renders a soft deleted staff member without losing their payslip', function () {
    [$staff, $payroll] = missingPayrollUserFixture($this);
    $staff->delete();
    $this->get('/payrolls?month=2026-11')->assertOk()->assertSee('Staf Riwayat')->assertSee('Rp2.050.000');
    expect($payroll->fresh()->total_amount)->toBe(2050000);
});

test('payroll renders an unavailable user relation without crashing or hiding the amount', function () {
    missingPayrollUserFixture($this);
    View::composer('pages.payrolls.index', function ($view) {
        $view->getData()['payrolls']->each(fn ($payroll) => $payroll->setRelation('user', null));
    });
    $this->get('/payrolls?month=2026-11')->assertOk()->assertSee('Staf tidak tersedia')->assertSee('Rp2.050.000');
});

test('generating and syncing an existing payslip updates the month without duplicates', function () {
    [$staff, $payroll] = missingPayrollUserFixture($this);
    $staff->update(['basic_salary' => 3000000]);
    $this->post(route('payrolls.generate'), ['month' => '2026-11'])->assertRedirect();
    $this->post(route('payrolls.generate'), ['month' => '2026-11'])->assertRedirect();
    $this->post(route('payrolls.sync-bonus'), ['month' => '2026-11'])->assertRedirect();
    expect(Payroll::count())->toBe(1)->and($payroll->fresh()->basic_salary)->toBe(3000000);
    $this->post(route('payrolls.generate'), ['month' => '2026-12'])->assertRedirect();
    expect(Payroll::count())->toBe(2)->and($payroll->fresh()->period_month->format('Y-m'))->toBe('2026-11');
});
