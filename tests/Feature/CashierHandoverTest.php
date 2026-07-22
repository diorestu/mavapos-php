<?php

use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
