<?php

use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('kasir menyimpan kewarganegaraan pembeli dari shopping cart saat checkout', function () {
    $cashier = User::factory()->create(['role' => 'owner']);
    $this->actingAs($cashier);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create([
        'user_id' => $cashier->id, 'sku' => 'NATION-001', 'name' => 'Kopi Turis',
        'buy_price' => 5000, 'sell_price' => 20000, 'stock' => 5, 'min_stock' => 0,
    ]);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 5]);
    $this->postJson(route('pos.shift.start'), ['opening_cash_amount' => 1000000])->assertOk();

    $checkout = $this->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-NATION-001', 'quantity' => 1]],
        'payment_method' => 'cash', 'paid_amount' => 20000,
        'buyer_nationality' => 'foreigner',
    ])->assertOk()
        ->assertJsonPath('sale.buyer_nationality', 'foreigner');
    $sale = PosSale::query()->where('invoice_number', $checkout->json('sale.invoice_number'))->firstOrFail();

    $this->assertDatabaseHas('pos_sales', ['id' => $sale->id, 'buyer_nationality' => 'foreigner']);
    $this->get(route('sales'))
        ->assertOk()
        ->assertSee('Pembeli Local')
        ->assertSee('Pembeli Foreigner')
        ->assertSee('1');
});
