<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('checkout menyimpan pelanggan dari data cart dan mengaitkannya ke penjualan', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $this->actingAs($cashier);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create([
        'user_id' => $cashier->id,
        'sku' => 'CUST-001',
        'name' => 'Produk Pelanggan',
        'buy_price' => 5000,
        'sell_price' => 20000,
        'stock' => 5,
        'min_stock' => 0,
    ]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 5]);

    $this->postJson(route('pos.shift.start'), ['opening_cash_amount' => 0])->assertOk();

    $this->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-CUST-001', 'quantity' => 1]],
        'customer_name' => 'Rina Pelanggan',
        'customer_phone' => '081234567890',
        'payment_method' => 'cash',
        'paid_amount' => 20000,
    ])->assertOk()
        ->assertJsonPath('sale.customer.name', 'Rina Pelanggan')
        ->assertJsonPath('sale.customer.phone', '081234567890');

    $customer = Customer::query()->where('phone', '081234567890')->firstOrFail();
    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Rina Pelanggan',
        'phone' => '081234567890',
    ]);
    $this->assertDatabaseHas('pos_sales', ['customer_id' => $customer->id]);
});
