<?php

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cashier can record one sale using cash and qris payments', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $this->actingAs($cashier);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create([
        'user_id' => $cashier->id,
        'sku' => 'SPLIT-300K',
        'name' => 'Paket Split Payment',
        'buy_price' => 100000,
        'sell_price' => 300000,
        'stock' => 3,
        'min_stock' => 0,
    ]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 3]);

    $this->postJson(route('pos.shift.start'), ['opening_cash_amount' => 100000])->assertOk();

    $this->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-SPLIT-300K', 'quantity' => 1]],
        'payment_method' => 'split',
        'payments' => [
            ['method' => 'cash', 'amount' => 200000],
            ['method' => 'qris', 'amount' => 100000],
        ],
        'buyer_nationality' => 'local',
    ])->assertOk()
        ->assertJsonPath('sale.payment_method', 'split')
        ->assertJsonPath('sale.payments.0.method', 'cash')
        ->assertJsonPath('sale.payments.0.amount', 200000)
        ->assertJsonPath('sale.payments.1.method', 'qris')
        ->assertJsonPath('sale.payments.1.amount', 100000);

    $this->assertDatabaseHas('pos_sale_payments', ['payment_method' => 'cash', 'amount' => 200000]);
    $this->assertDatabaseHas('pos_sale_payments', ['payment_method' => 'qris', 'amount' => 100000]);

    $shift = CashierShift::query()->firstOrFail();
    expect($shift->cash_total)->toBe(200000)
        ->and($shift->qris_total)->toBe(100000)
        ->and($shift->card_total)->toBe(0);
});

test('split payment must exactly match the sale total', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $this->actingAs($cashier);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create([
        'user_id' => $cashier->id,
        'sku' => 'SPLIT-EXACT',
        'name' => 'Paket Tepat',
        'buy_price' => 50000,
        'sell_price' => 300000,
        'stock' => 3,
        'min_stock' => 0,
    ]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 3]);
    $this->postJson(route('pos.shift.start'), ['opening_cash_amount' => 0])->assertOk();

    $this->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-SPLIT-EXACT', 'quantity' => 1]],
        'payment_method' => 'split',
        'payments' => [
            ['method' => 'cash', 'amount' => 200000],
            ['method' => 'card', 'amount' => 50000],
        ],
        'buyer_nationality' => 'local',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Total split payment harus sama dengan total transaksi.');
});
