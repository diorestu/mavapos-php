<?php

use App\Models\Branch;
use App\Models\User;
use App\Models\CashierShift;
use App\Services\SalesBonusService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('user kasir menyimpan cabang tugasnya saat dibuat', function () {
    $owner = User::query()->where('email', 'test@example.com')->firstOrFail();
    $branch = Branch::query()->create([
        'user_id' => $owner->id,
        'name' => 'Cabang Kasir',
        'code' => 'cabang-kasir',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('users.store'), [
            'name' => 'Kasir Cabang',
            'email' => 'kasir-cabang@example.com',
            'role' => 'kasir',
            'branch_id' => $branch->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'kasir-cabang@example.com',
        'role' => 'kasir',
        'branch_id' => $branch->id,
    ]);
});

test('kasir tidak dapat memulai shift di luar cabang tugasnya', function () {
    $owner = User::query()->where('email', 'test@example.com')->firstOrFail();
    $assignedBranch = Branch::query()->create([
        'user_id' => $owner->id,
        'name' => 'Cabang Tugas',
        'code' => 'cabang-tugas',
        'is_active' => true,
    ]);
    $otherBranch = Branch::query()->create([
        'user_id' => $owner->id,
        'name' => 'Cabang Lain',
        'code' => 'cabang-lain',
        'is_active' => true,
    ]);
    $cashier = User::factory()->create([
        'role' => 'kasir',
        'tenant_owner_id' => $owner->id,
        'branch_id' => $assignedBranch->id,
    ]);

    $this->actingAs($owner)
        ->post(route('branches.switch'), ['branch_id' => $otherBranch->id])
        ->assertRedirect();

    $this->actingAs($cashier)
        ->post(route('branches.switch'), ['branch_id' => $otherBranch->id])
        ->assertForbidden();

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'))
        ->assertOk()
        ->assertJsonPath('shift.branch', $assignedBranch->name);
});

test('capaian per orang tidak memasukkan kasir dari cabang lain', function () {
    $owner = User::query()->where('email', 'test@example.com')->firstOrFail();
    $activeBranch = Branch::query()->create(['user_id' => $owner->id, 'name' => 'Cabang Aktif', 'code' => 'cabang-aktif', 'is_active' => true]);
    $otherBranch = Branch::query()->create(['user_id' => $owner->id, 'name' => 'Cabang Lain', 'code' => 'cabang-lain', 'is_active' => true]);
    $activeCashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $activeBranch->id]);
    $otherCashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $otherBranch->id]);

    CashierShift::query()->create(['user_id' => $activeCashier->id, 'branch_id' => $activeBranch->id, 'opened_at' => now()]);
    CashierShift::query()->create(['user_id' => $otherCashier->id, 'branch_id' => $activeBranch->id, 'opened_at' => now(), 'companion_staff_ids' => [$otherCashier->id]]);

    expect(app(SalesBonusService::class)->forBranchDay($activeBranch->id, now())['staffIds'])
        ->toContain($activeCashier->id)
        ->not->toContain($otherCashier->id);
});
