<?php

use App\Models\Branch;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function onlineMerchantFixture($test, string $role = 'owner'): array
{
    $owner = User::factory()->create(['role' => $role]);
    $test->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create(['sku' => 'ONLINE-COFFEE', 'name' => 'Kopi Online', 'sell_price' => 20000, 'buy_price' => 5000, 'stock' => 10, 'min_stock' => 0]);
    $inventory = app(BranchInventoryManager::class)->forProduct($branch->id, $product);
    $inventory->update(['stock' => 10]);
    $test->postJson(route('pos.shift.start'), ['opening_cash_amount' => 0])->assertOk();

    return [$inventory, ['items' => [['id' => 'product-ONLINE-COFFEE', 'quantity' => 1]], 'payment_method' => 'qris']];
}

test('online merchant is disabled by default and settings are isolated by branch and tenant', function () {
    onlineMerchantFixture($this);
    $first = StoreSetting::current();
    expect($first->cashier_online_merchant_enabled)->toBeFalse();
    $this->patch(route('settings.update'), ['store_name' => 'Toko', 'cashier_online_merchant_enabled' => '1'])->assertRedirect(route('settings'));
    expect($first->fresh()->cashier_online_merchant_enabled)->toBeTrue();
    $second = Branch::query()->create(['name' => 'Kedua', 'code' => 'online-second', 'is_active' => true]);
    app(BranchContext::class)->setActive($second->id);
    expect(StoreSetting::current()->cashier_online_merchant_enabled)->toBeFalse();
    $this->actingAs(User::factory()->create(['role' => 'owner']));
    expect(StoreSetting::current()->cashier_online_merchant_enabled)->toBeFalse();
    expect($first->fresh()->cashier_online_merchant_enabled)->toBeTrue();
});

test('online checkout records each merchant while retaining payment and inventory calculations', function (string $merchant, string $label) {
    [$inventory, $payload] = onlineMerchantFixture($this);
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $response = $this->postJson(route('pos.checkout'), [...$payload, 'sales_channel' => 'online', 'online_merchant' => $merchant])->assertOk()
        ->assertJsonPath('sale.online_merchant', $merchant)->assertJsonPath('sale.online_merchant_label', $label)
        ->assertJsonPath('sale.payment_method', 'qris')->assertJsonPath('sale.total', 20000);
    $sale = PosSale::query()->where('invoice_number', $response->json('sale.invoice_number'))->firstOrFail();
    expect($sale->online_merchant)->toBe($merchant)->and($inventory->fresh()->stock)->toBe(9);
    $this->get(route('sales'))->assertOk()->assertSee($label);
})->with([['shopeefood', 'ShopeeFood'], ['grabfood', 'GrabFood'], ['gofood', 'GoFood by Gojek'], ['tiktokfood', 'TiktokFood']]);

test('disabled or invalid online checkout has no sale or stock mutation', function () {
    [$inventory, $payload] = onlineMerchantFixture($this);
    $this->postJson(route('pos.checkout'), [...$payload, 'sales_channel' => 'online', 'online_merchant' => 'grabfood'])->assertUnprocessable();
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson(route('pos.checkout'), [...$payload, 'sales_channel' => 'online'])->assertUnprocessable()->assertJsonValidationErrors('online_merchant');
    $this->postJson(route('pos.checkout'), [...$payload, 'online_merchant' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('online_merchant');
    expect(PosSale::query()->count())->toBe(0)->and($inventory->fresh()->stock)->toBe(10);
    $this->postJson(route('pos.checkout'), $payload)->assertOk()->assertJsonPath('sale.online_merchant', null);
});

test('corrections preserve historical merchants when disabled and reject adding or changing them', function () {
    [$inventory, $payload] = onlineMerchantFixture($this, 'admin');
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson(route('pos.checkout'), [...$payload, 'online_merchant' => 'grabfood'])->assertOk();
    $sale = PosSale::query()->firstOrFail();
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => false]);
    $edit = [...$payload, 'reason' => 'Koreksi jumlah', 'items' => [['id' => 'product-ONLINE-COFFEE', 'quantity' => 2]]];
    $this->putJson(route('sales.update', $sale), $edit)->assertOk();
    expect($sale->fresh()->online_merchant)->toBe('grabfood');
    $this->putJson(route('sales.update', $sale), [...$edit, 'online_merchant' => 'grabfood'])->assertOk();
    $this->putJson(route('sales.update', $sale), [...$edit, 'online_merchant' => 'gofood'])->assertUnprocessable();
    expect($sale->fresh()->online_merchant)->toBe('grabfood')->and($inventory->fresh()->stock)->toBe(8);
    $this->putJson(route('sales.update', $sale), [...$edit, 'online_merchant' => null])->assertOk();
    expect($sale->fresh()->online_merchant)->toBeNull();
    $this->putJson(route('sales.update', $sale), [...$edit, 'online_merchant' => 'grabfood'])->assertUnprocessable();
});
