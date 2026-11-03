<?php

use App\Models\CashierShift;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('handover only records successful reconciliation when both amounts match while opening cash remains independent', function (array $declared, bool $matched) {
    $this->travelTo(now()->setTime(12, 0));
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $cashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $branch->id]);
    $previous = CashierShift::query()->create([
        'user_id' => $owner->id, 'branch_id' => $branch->id,
        'opened_at' => now()->subHours(2), 'closed_at' => now()->subHour(), 'opening_cash_amount' => 100000,
    ]);

    $this->actingAs($cashier)->postJson(route('pos.shift.start'), ['opening_cash_amount' => 20000, ...$declared])
        ->assertOk()->assertJsonPath('shift.openingCashAmount', 20000);
    $next = CashierShift::query()->where('user_id', $cashier->id)->firstOrFail();
    expect($next->previous_cashier_shift_id)->toBe($previous->id);
    expect($next->validated_cash_amount)->toBe(isset($declared['validated_cash_amount']) ? (int) $declared['validated_cash_amount'] : null);
    expect($next->validated_card_amount)->toBe(isset($declared['validated_card_amount']) ? (int) $declared['validated_card_amount'] : null);
    expect($next->handover_validated_at !== null)->toBe($matched);
})->with([
    'matching numeric amounts' => [['validated_cash_amount' => 100000, 'validated_card_amount' => 0], true],
    'matching string amounts' => [['validated_cash_amount' => '100000', 'validated_card_amount' => '0'], true],
    'cash mismatch' => [['validated_cash_amount' => 99000, 'validated_card_amount' => 0], false],
    'card mismatch' => [['validated_cash_amount' => 100000, 'validated_card_amount' => 1000], false],
    'cash only' => [['validated_cash_amount' => 100000], false],
    'card only' => [['validated_card_amount' => 0], false],
    'no reconciliation amounts' => [[], false],
]);

test('staff baru dapat melakukan ganti shift tanpa checklist dan semua petugas hari itu tetap tercatat', function () {
    $owner = User::factory()->create(['role' => 'owner', 'name' => 'Kasir Pagi']);
    $newCashier = User::factory()->create(['role' => 'kasir', 'name' => 'Kasir Siang', 'password' => 'password', 'tenant_owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->postJson(route('pos.shift.start'), [
            'opening_cash_amount' => 50000,
            'companion_staff_ids' => [$newCashier->id],
            'opening_checklist' => ['cash', 'printer'],
        ])->assertOk();

    $this->actingAs($owner)
        ->postJson(route('pos.shift.change'), [
            'cashier_user_id' => $newCashier->id,
            'cashier_password' => 'password',
            'companion_staff_ids' => [$owner->id],
        ])->assertOk()
        ->assertJsonPath('shift.cashier', 'Kasir Siang');

    $oldShift = CashierShift::query()->where('user_id', $owner->id)->firstOrFail();
    $newShift = CashierShift::query()->where('user_id', $newCashier->id)->firstOrFail();

    expect($oldShift->closed_at)->not->toBeNull()
        ->and($newShift->previous_cashier_shift_id)->toBe($oldShift->id)
        ->and($newShift->companion_staff_ids)->toContain($owner->id)
        ->and($oldShift->opening_checklist)->toBe(['cash', 'printer']);
});

test('kasir pengganti dapat diverifikasi tanpa mengganti akun login POS', function () {
    $owner = User::factory()->create(['role' => 'owner', 'name' => 'Kasir Pagi', 'password' => 'password']);
    $newCashier = User::factory()->create(['role' => 'kasir', 'name' => 'Kasir Siang', 'password' => 'password', 'tenant_owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->postJson(route('pos.shift.start'), [
            'opening_cash_amount' => 50000,
            'opening_checklist' => ['cash'],
        ])->assertOk();

    $this->actingAs($owner)
        ->postJson(route('pos.shift.change'), [
            'cashier_user_id' => $newCashier->id,
            'cashier_password' => 'password',
        ])->assertOk()
        ->assertJsonPath('shift.cashier', 'Kasir Siang');

    $this->assertAuthenticatedAs($owner);

    $newShift = CashierShift::query()->where('user_id', $newCashier->id)->firstOrFail();

    expect($newShift->closed_at)->toBeNull();
});
