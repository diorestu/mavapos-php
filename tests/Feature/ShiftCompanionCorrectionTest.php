<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Payroll;
use App\Models\PosSale;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\SalesBonusService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function companionCorrectionFixture($test, string $role = 'owner'): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $actor = $role === 'owner' ? $owner : User::factory()->create(['role' => $role, 'tenant_owner_id' => $owner->id]);
    $test->actingAs($actor);
    $branch = app(BranchContext::class)->active();
    $cashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $branch->id]);
    $companion = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $branch->id, 'name' => 'Pendamping Keliru']);
    $shift = CashierShift::create(['user_id' => $cashier->id, 'branch_id' => $branch->id, 'companion_staff_ids' => [$companion->id], 'opened_at' => now()->subHour(), 'opening_cash_amount' => 100000]);
    $sale = PosSale::create(['user_id' => $cashier->id, 'cashier_shift_id' => $shift->id, 'branch_id' => $branch->id, 'invoice_number' => 'COMPANION-CORRECTION', 'payment_method' => 'cash', 'subtotal' => 10000, 'total' => 10000, 'sold_at' => now()]);

    return compact('owner', 'actor', 'branch', 'cashier', 'companion', 'shift', 'sale');
}

test('owner and admin can clear mistaken companions without closing or changing a sale', function (string $role) {
    $f = companionCorrectionFixture($this, $role);
    $beforeShift = $f['shift']->fresh()->getAttributes();
    $beforeSale = $f['sale']->fresh()->getAttributes();
    $this->get(route('cashier-shifts'))->assertOk()->assertSee('Hapus Pendamping')->assertSee('Pendamping Keliru');
    $url = '/cashier-shifts/'.$f['shift']->id.'/companions';
    $this->delete($url)->assertRedirect(route('cashier-shifts'))->assertSessionHas('success');
    $shift = $f['shift']->fresh();
    expect($shift->companion_staff_ids)->toBe([])->and($shift->closed_at)->toBeNull()
        ->and(collect($shift->getAttributes())->except(['companion_staff_ids', 'updated_at'])->all())->toBe(collect($beforeShift)->except(['companion_staff_ids', 'updated_at'])->all())
        ->and($f['sale']->fresh()->getAttributes())->toBe($beforeSale);
    $this->delete($url)->assertRedirect(route('cashier-shifts'));
    $this->get(route('cashier-shifts'))->assertOk()->assertDontSee('Hapus Pendamping');
})->with(['owner', 'admin']);

test('cashier cannot clear companions and foreign branch shifts cannot be changed', function () {
    $f = companionCorrectionFixture($this);
    $url = '/cashier-shifts/'.$f['shift']->id.'/companions';
    $this->actingAs($f['cashier'])->deleteJson($url)->assertForbidden();
    $this->get(route('cashier-shifts'))->assertOk()->assertDontSee('Hapus Pendamping');
    $other = User::factory()->create(['role' => 'owner']);
    $this->actingAs($other)->deleteJson($url)->assertNotFound();
    expect($f['shift']->fresh()->companion_staff_ids)->toBe([$f['companion']->id]);
});

test('companion correction is constrained to the currently selected outlet', function () {
    $f = companionCorrectionFixture($this);
    $second = Branch::create(['user_id' => $f['owner']->id, 'name' => 'Outlet B', 'code' => 'COMPANION-B', 'is_active' => true]);
    app(BranchContext::class)->setActive($second->id);
    $this->deleteJson('/cashier-shifts/'.$f['shift']->id.'/companions')->assertNotFound();
    expect($f['shift']->fresh()->companion_staff_ids)->toBe([$f['companion']->id]);
});

test('removing companions corrects daily attendance bonus while preserving an existing payslip until sync', function () {
    $f = companionCorrectionFixture($this);
    StoreSetting::current()->update(['sales_bonus_tiers' => [['minimum' => 1, 'reward' => 25000]]]);
    $service = app(SalesBonusService::class);
    $day = now('Asia/Makassar');
    expect($service->forBranchDay($f['branch']->id, $day)['staffIds'])->toContain($f['companion']->id);
    $payroll = Payroll::create(['user_id' => $f['companion']->id, 'branch_id' => $f['branch']->id, 'period_month' => $day->copy()->startOfMonth()->toDateString(), 'sales_bonus' => 25000, 'total_amount' => 25000]);
    $this->delete('/cashier-shifts/'.$f['shift']->id.'/companions')->assertRedirect(route('cashier-shifts'));
    expect($service->forBranchDay($f['branch']->id, $day)['staffIds'])->not->toContain($f['companion']->id)
        ->and($payroll->fresh()->sales_bonus)->toBe(25000);
    $this->post(route('payrolls.sync-bonus'), ['month' => $day->format('Y-m')])->assertRedirect();
    expect($payroll->fresh()->sales_bonus)->toBe(0);
});

test('owner can correct a closed shift without reopening it', function () {
    $f = companionCorrectionFixture($this);
    $f['shift']->update(['closed_at' => now()]);
    $closedAt = $f['shift']->fresh()->closed_at->toIso8601String();
    $this->delete('/cashier-shifts/'.$f['shift']->id.'/companions')->assertRedirect(route('cashier-shifts'));
    expect($f['shift']->fresh()->companion_staff_ids)->toBe([])
        ->and($f['shift']->fresh()->closed_at->toIso8601String())->toBe($closedAt);
});
