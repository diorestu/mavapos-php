<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('global search menampilkan menu produk dan invoice', function () {
    $cashier = User::query()->where('email', 'test@example.com')->firstOrFail();
    $cashier->update(['name' => 'Kasir Search']);

    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();

    $this->actingAs($cashier)
        ->getJson(route('global-search', ['q' => 'kasir']))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'menu',
            'title' => 'Kasir',
        ]);

    $this->actingAs($cashier)
        ->getJson(route('global-search', ['q' => $product->sku]))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'product',
            'title' => $product->name,
        ]);

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $checkout = $this->actingAs($cashier)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-'.$product->sku, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => $product->sell_price,
        ])
        ->assertOk();

    $invoice = $checkout->json('sale.invoice_number');

    $this->actingAs($cashier)
        ->getJson(route('global-search', ['q' => $invoice]))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'sale',
            'title' => $invoice,
        ]);
});
