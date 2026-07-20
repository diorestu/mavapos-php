<?php

use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('kasir dapat mencatat pemberian gratis dan stok tetap berkurang', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $this->actingAs($cashier);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create([
        'user_id' => $cashier->id,
        'sku' => 'FREE-001',
        'name' => 'Kopi Gratis',
        'buy_price' => 5000,
        'sell_price' => 20000,
        'stock' => 5,
        'min_stock' => 0,
    ]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 5]);

    $this->postJson(route('pos.shift.start'), ['opening_cash_amount' => 0])->assertOk();

    $this->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-FREE-001', 'quantity' => 1]],
        'payment_method' => 'free',
        'complimentary_category' => 'influencer',
        'complimentary_recipient_name' => 'Dina Creator',
    ])
        ->assertOk()
        ->assertJsonPath('sale.payment_method', 'free')
        ->assertJsonPath('sale.complimentary_category', 'influencer')
        ->assertJsonPath('sale.complimentary_recipient_name', 'Dina Creator');

    expect(app(BranchInventoryManager::class)->stockForProduct($branch->id, $product->fresh()))->toBe(4);
    $this->assertDatabaseHas('pos_sales', [
        'payment_method' => 'free',
        'complimentary_category' => 'influencer',
        'complimentary_recipient_name' => 'Dina Creator',
        'total' => 0,
        'paid_amount' => 0,
    ]);
    $this->assertDatabaseHas('customers', [
        'name' => 'Dina Creator',
        'status' => 'aktif',
    ]);

    $this->get(route('sales'))
        ->assertOk()
        ->assertSee('Gratis')
        ->assertSee('Influencer')
        ->assertSee('Dina Creator');
});
