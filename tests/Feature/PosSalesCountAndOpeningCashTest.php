<?php

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('checkout tiga cup menghitung tiga cup terjual pada rekap shift', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $this->actingAs($cashier);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create([
        'user_id' => $cashier->id, 'sku' => 'CUP-003', 'name' => 'Es Kopi',
        'buy_price' => 5000, 'sell_price' => 20000, 'stock' => 10, 'min_stock' => 0,
    ]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 10]);

    $this->postJson(route('pos.shift.start'), ['opening_cash_amount' => 1000000])->assertOk();
    $this->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-CUP-003', 'quantity' => 3]],
        'payment_method' => 'cash', 'paid_amount' => 60000,
    ])->assertOk()->assertJsonPath('shift.salesCount', 3);

    expect(CashierShift::query()->firstOrFail()->sales_count)->toBe(3);
    $this->get(route('sales'))
        ->assertOk()
        ->assertSee('Cup / Item Terjual')
        ->assertSee('3 cup/item dalam periode');
});

test('buka kasir pagi tidak membawa kas dari penutupan hari sebelumnya', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $this->actingAs($cashier);
    $branch = app(BranchContext::class)->active();
    CashierShift::query()->create([
        'user_id' => $cashier->id, 'branch_id' => $branch->id,
        'opened_at' => now()->subDay()->startOfDay(), 'closed_at' => now()->subDay()->endOfDay(),
        'opening_cash_amount' => 1000000, 'cash_total' => 6240000,
    ]);

    $this->postJson(route('pos.shift.start'), ['opening_cash_amount' => 1000000])
        ->assertOk()
        ->assertJsonPath('shift.openingCashAmount', 1000000);
});
