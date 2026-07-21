<?php

use App\Models\Customer;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loyaltyCustomer(User $cashier, array $attributes = []): Customer
{
    return Customer::query()->create([...['user_id' => $cashier->id, 'code' => 'CUST-LOYALTY-'.str()->upper(str()->random(8)), 'name' => 'Nina', 'phone' => '08123456789', 'status' => 'aktif'], ...$attributes]);
}

function loyaltyCheckout($test, User $cashier, array $payload): PosSale
{
    $response = $test->actingAs($cashier)->postJson(route('pos.checkout'), $payload)->assertOk();

    return PosSale::query()->where('invoice_number', $response->json('sale.invoice_number'))->firstOrFail();
}

test('purchase does not receive a stamp or discount unless cashier selects the physical loyalty card', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create(['user_id' => $cashier->id, 'sku' => 'LOYALTY-NONE', 'name' => 'Kopi Loyalitas', 'buy_price' => 5000, 'sell_price' => 20000, 'stock' => 8, 'min_stock' => 0]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 8]);
    $customer = loyaltyCustomer($cashier);

    $this->actingAs($cashier)->postJson(route('pos.shift.start'), ['opening_cash_amount' => 100000])->assertOk();
    $sale = loyaltyCheckout($this, $cashier, ['items' => [['id' => 'product-LOYALTY-NONE', 'quantity' => 3]], 'payment_method' => 'qris', 'customer_name' => $customer->name, 'customer_phone' => $customer->phone, 'buyer_nationality' => 'local']);

    expect($sale->discount)->toBe(0)
        ->and($customer->fresh()->loyalty_stamp_count)->toBe(0);
});

test('five cups in one stamped purchase unlock fifty percent reward for the next purchase only', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create(['user_id' => $cashier->id, 'sku' => 'LOYALTY-FIVE', 'name' => 'Kopi Lima', 'buy_price' => 5000, 'sell_price' => 20000, 'stock' => 8, 'min_stock' => 0]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 8]);
    $customer = loyaltyCustomer($cashier);

    $this->actingAs($cashier)->postJson(route('pos.shift.start'), ['opening_cash_amount' => 100000])->assertOk();
    $fifthStampSale = loyaltyCheckout($this, $cashier, ['items' => [['id' => 'product-LOYALTY-FIVE', 'quantity' => 5]], 'payment_method' => 'qris', 'customer_name' => $customer->name, 'customer_phone' => $customer->phone, 'buyer_nationality' => 'local', 'loyalty_stamp' => true]);

    expect($fifthStampSale->discount)->toBe(0)
        ->and($customer->fresh()->loyalty_stamp_count)->toBe(5)
        ->and($customer->fresh()->loyalty_fifty_reward_available)->toBeTrue();

    $rewardSale = loyaltyCheckout($this, $cashier, ['items' => [['id' => 'product-LOYALTY-FIVE', 'quantity' => 1]], 'payment_method' => 'qris', 'customer_name' => $customer->name, 'customer_phone' => $customer->phone, 'buyer_nationality' => 'local', 'loyalty_reward' => 'fifty_percent']);

    expect($rewardSale->discount)->toBe(10000)
        ->and($customer->fresh()->loyalty_stamp_count)->toBe(5)
        ->and($customer->fresh()->loyalty_fifty_reward_available)->toBeFalse();
});

test('tenth physical stamp unlocks one free cup for the next purchase', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $branch = app(BranchContext::class)->active();
    $cheap = Product::query()->create(['user_id' => $cashier->id, 'sku' => 'LOYALTY-TEN-CHEAP', 'name' => 'Kopi Kecil', 'buy_price' => 5000, 'sell_price' => 10000, 'stock' => 5, 'min_stock' => 0]);
    $expensive = Product::query()->create(['user_id' => $cashier->id, 'sku' => 'LOYALTY-TEN-EXPENSIVE', 'name' => 'Kopi Besar', 'buy_price' => 5000, 'sell_price' => 20000, 'stock' => 5, 'min_stock' => 0]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $cheap)->update(['stock' => 5]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $expensive)->update(['stock' => 5]);
    $customer = loyaltyCustomer($cashier, ['loyalty_stamp_count' => 9]);

    $this->actingAs($cashier)->postJson(route('pos.shift.start'), ['opening_cash_amount' => 100000])->assertOk();
    loyaltyCheckout($this, $cashier, ['items' => [['id' => 'product-LOYALTY-TEN-CHEAP', 'quantity' => 1]], 'payment_method' => 'qris', 'customer_name' => $customer->name, 'customer_phone' => $customer->phone, 'buyer_nationality' => 'local', 'loyalty_stamp' => true]);
    expect($customer->fresh()->loyalty_free_reward_available)->toBeTrue();

    $rewardSale = loyaltyCheckout($this, $cashier, ['items' => [['id' => 'product-LOYALTY-TEN-CHEAP', 'quantity' => 1], ['id' => 'product-LOYALTY-TEN-EXPENSIVE', 'quantity' => 1]], 'payment_method' => 'qris', 'customer_name' => $customer->name, 'customer_phone' => $customer->phone, 'buyer_nationality' => 'local', 'loyalty_reward' => 'free_cup']);

    expect($rewardSale->discount)->toBe(10000)
        ->and($customer->fresh()->loyalty_stamp_count)->toBe(0)
        ->and($customer->fresh()->loyalty_free_reward_available)->toBeFalse();
});
