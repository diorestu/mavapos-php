<?php

use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff baru dapat melakukan ganti shift tanpa checklist dan semua petugas hari itu tetap tercatat', function () {
    $owner = User::factory()->create(['role' => 'owner', 'name' => 'Kasir Pagi']);
    $newCashier = User::factory()->create(['role' => 'kasir', 'name' => 'Kasir Siang']);

    $this->actingAs($owner)
        ->postJson(route('pos.shift.start'), [
            'opening_cash_amount' => 50000,
            'companion_staff_ids' => [$newCashier->id],
            'opening_checklist' => ['cash', 'printer'],
        ])->assertOk();

    $this->actingAs($newCashier)
        ->postJson(route('pos.shift.change'), [
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
