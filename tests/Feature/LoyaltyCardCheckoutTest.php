<?php

use App\Models\Customer;
use App\Models\Branch;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('loyalty setting can be disabled and enabled independently for each branch and tenant', function () {
    [$customer, $payload] = physicalFifthFixture($this);
    $firstSetting = StoreSetting::current();
    $firstBranchId = $firstSetting->branch_id;
    $secondBranch = Branch::query()->create(['name' => 'Cabang Kedua', 'code' => 'loyalty-second', 'is_active' => true]);

    $this->patch(route('settings.update'), ['store_name' => 'Toko Uji'])->assertRedirect(route('settings'));
    expect($firstSetting->fresh()->cashier_loyalty_card_enabled)->toBeFalse();

    app(BranchContext::class)->setActive($secondBranch->id);
    expect(StoreSetting::current()->cashier_loyalty_card_enabled)->toBeTrue();
    app(BranchContext::class)->setActive($firstBranchId);
    $this->patch(route('settings.update'), ['store_name' => 'Toko Uji', 'cashier_loyalty_card_enabled' => '1'])->assertRedirect(route('settings'));
    expect($firstSetting->fresh()->cashier_loyalty_card_enabled)->toBeTrue();

    $otherOwner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($otherOwner)->patch(route('settings.update'), ['store_name' => 'Toko Lain'])->assertRedirect(route('settings'));
    expect(StoreSetting::current()->cashier_loyalty_card_enabled)->toBeFalse()
        ->and($firstSetting->fresh()->cashier_loyalty_card_enabled)->toBeTrue();
});

test('disabled loyalty setting rejects reward checkout without changing customers or sales', function (string $reward) {
    [$customer, $payload] = physicalFifthFixture($this, ['loyalty_fifty_reward_available' => true, 'loyalty_free_reward_available' => true]);
    StoreSetting::current()->update(['cashier_loyalty_card_enabled' => false]);

    $this->postJson(route('pos.checkout'), [...$payload, 'loyalty_reward' => $reward])->assertUnprocessable();

    expect(PosSale::query()->count())->toBe(0)
        ->and($customer->fresh()->loyalty_stamp_count)->toBe(0)
        ->and($customer->fresh()->loyalty_fifty_reward_available)->toBeTrue()
        ->and($customer->fresh()->loyalty_free_reward_available)->toBeTrue();
})->with(['physical_fifth', 'physical_tenth', 'fifty_percent', 'free_cup']);

test('disabled loyalty blocks new stamps but permits an ordinary manual discount', function () {
    [$customer, $payload] = physicalFifthFixture($this);
    StoreSetting::current()->update(['cashier_loyalty_card_enabled' => false]);
    $this->postJson(route('pos.checkout'), [...$payload, 'loyalty_reward' => null, 'loyalty_stamp' => true])->assertUnprocessable();
    expect($customer->fresh()->loyalty_stamp_count)->toBe(0);

    $this->postJson(route('pos.checkout'), [...$payload, 'loyalty_reward' => null])->assertOk()->assertJsonPath('sale.discount', 7000);
});

function physicalFifthFixture($test, array $attributes = []): array
{
    $cashier = User::factory()->create(['role' => 'owner']);
    $test->actingAs($cashier);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create(['user_id' => $cashier->id, 'sku' => 'PHYSICAL-FIFTH', 'name' => 'Kopi', 'sell_price' => 20000, 'buy_price' => 5000, 'stock' => 10, 'min_stock' => 0]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 10]);
    $customer = loyaltyCustomer($cashier, $attributes);
    $test->postJson(route('pos.shift.start'), ['opening_cash_amount' => 100000])->assertOk();

    return [$customer, ['items' => [['id' => 'product-PHYSICAL-FIFTH', 'quantity' => 1]], 'payment_method' => 'qris', 'customer_phone' => $customer->phone, 'discount' => 7000, 'loyalty_reward' => 'physical_fifth']];
}

test('physical fifth stamp applies fifty percent regardless of the entered discount and consumes the reward immediately', function (int $stamps) {
    [$customer, $payload] = physicalFifthFixture($this, ['loyalty_stamp_count' => $stamps]);

    $response = $this->postJson(route('pos.checkout'), $payload)->assertOk();
    $sale = PosSale::query()->where('invoice_number', $response->json('sale.invoice_number'))->firstOrFail();

    expect($sale->customer_id)->toBe($customer->id)
        ->and($sale->loyalty_reward)->toBe('physical_fifth')
        ->and($sale->discount)->toBe(10000)
        ->and($sale->total)->toBe(10000)
        ->and($customer->fresh()->loyalty_stamp_count)->toBe(5)
        ->and($customer->fresh()->loyalty_fifty_reward_available)->toBeFalse();

    $this->postJson(route('pos.checkout'), $payload)->assertUnprocessable();
    expect(PosSale::query()->count())->toBe(1);
})->with([0, 4]);

test('physical fifth stamp never reduces a higher digital stamp count or adds purchase stamps twice', function () {
    [$customer, $payload] = physicalFifthFixture($this, ['loyalty_stamp_count' => 8, 'loyalty_fifty_reward_available' => true]);
    $this->postJson(route('pos.checkout'), [...$payload, 'loyalty_stamp' => true])->assertOk();

    expect($customer->fresh()->loyalty_stamp_count)->toBe(8)
        ->and($customer->fresh()->loyalty_fifty_reward_available)->toBeFalse();
});

test('physical fifth stamp requires a phone but calculates its discount without manual input', function () {
    [$customer, $payload] = physicalFifthFixture($this);
    $this->postJson(route('pos.checkout'), [...$payload, 'customer_phone' => null])->assertUnprocessable();
    expect($customer->fresh()->loyalty_stamp_count)->toBe(0)
        ->and(PosSale::query()->count())->toBe(0);

    $this->postJson(route('pos.checkout'), [...$payload, 'discount' => 0])->assertOk()->assertJsonPath('sale.discount', 10000);
});

test('physical tenth stamp grants exactly one cheapest cup and starts the next card cycle', function () {
    [$customer, $payload] = physicalFifthFixture($this, ['loyalty_stamp_count' => 5]);
    $cheap = Product::query()->create(['sku' => 'PHYSICAL-CHEAP', 'name' => 'Cup Kecil', 'buy_price' => 5000, 'sell_price' => 12000, 'stock' => 10, 'min_stock' => 0]);
    app(BranchInventoryManager::class)->forProduct(app(BranchContext::class)->activeId(), $cheap)->update(['stock' => 10]);
    $response = $this->postJson(route('pos.checkout'), [...$payload, 'items' => [['id' => 'product-PHYSICAL-FIFTH', 'quantity' => 1], ['id' => 'product-PHYSICAL-CHEAP', 'quantity' => 2]], 'loyalty_reward' => 'physical_tenth', 'discount' => 44000, 'loyalty_stamp' => true])->assertOk();

    expect($response->json('sale.discount'))->toBe(12000)
        ->and($response->json('sale.total'))->toBe(32000)
        ->and($customer->fresh()->loyalty_stamp_count)->toBe(0)
        ->and($customer->fresh()->loyalty_fifty_reward_available)->toBeFalse()
        ->and($customer->fresh()->loyalty_free_reward_available)->toBeFalse();

    $this->postJson(route('pos.checkout'), $payload)->assertOk()->assertJsonPath('sale.discount', 10000);
});

test('failed payment rolls back the physical fifth stamp and discount', function () {
    [$customer, $payload] = physicalFifthFixture($this);
    $this->postJson(route('pos.checkout'), [...$payload, 'payment_method' => 'cash', 'paid_amount' => 0])->assertUnprocessable();

    expect($customer->fresh()->loyalty_stamp_count)->toBe(0)
        ->and($customer->fresh()->loyalty_fifty_reward_available)->toBeFalse()
        ->and(PosSale::query()->count())->toBe(0);
});

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
