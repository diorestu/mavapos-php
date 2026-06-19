<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('produk difilter dari backend berdasarkan search kategori dan status', function () {
    $user = User::factory()->create();
    $food = ProductCategory::query()->create([
        'name' => 'Makanan',
        'code' => 'makanan',
        'status' => 'aktif',
        'product_count' => 0,
    ]);
    $drink = ProductCategory::query()->create([
        'name' => 'Minuman',
        'code' => 'minuman',
        'status' => 'aktif',
        'product_count' => 0,
    ]);

    Product::query()->create([
        'product_category_id' => $drink->id,
        'name' => 'Kopi Filter',
        'sku' => 'KOPI-FILTER',
        'sell_price' => 18000,
        'stock' => 4,
        'min_stock' => 10,
    ]);
    Product::query()->create([
        'product_category_id' => $food->id,
        'name' => 'Roti Tawar',
        'sku' => 'ROTI-TAWAR',
        'sell_price' => 12000,
        'stock' => 12,
        'min_stock' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('products', [
            'search' => 'kopi',
            'category' => 'minuman',
            'status' => 'stok-menipis',
        ]))
        ->assertOk()
        ->assertSee('Kopi Filter')
        ->assertDontSee('Roti Tawar');
});

test('kategori produk difilter dari backend berdasarkan search dan status', function () {
    $user = User::factory()->create();

    ProductCategory::query()->create([
        'name' => 'Kategori Aktif',
        'code' => 'kategori-aktif',
        'status' => 'aktif',
        'product_count' => 2,
    ]);
    ProductCategory::query()->create([
        'name' => 'Kategori Nonaktif',
        'code' => 'kategori-nonaktif',
        'status' => 'nonaktif',
        'product_count' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('product-categories', [
            'search' => 'aktif',
            'status' => 'nonaktif',
        ]))
        ->assertOk()
        ->assertSee('Kategori Nonaktif')
        ->assertDontSee('Kategori Aktif');
});

test('stok difilter dari backend berdasarkan search dan status', function () {
    $user = User::factory()->create();

    Product::query()->create([
        'name' => 'Produk Habis',
        'sku' => 'HABIS-001',
        'sell_price' => 10000,
        'stock' => 0,
        'min_stock' => 2,
    ]);
    Product::query()->create([
        'name' => 'Produk Aman',
        'sku' => 'AMAN-001',
        'sell_price' => 10000,
        'stock' => 20,
        'min_stock' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('inventory', [
            'search' => 'produk',
            'status' => 'habis',
        ]))
        ->assertOk()
        ->assertSee('Produk Habis')
        ->assertDontSee('Produk Aman');
});

test('pelanggan dan supplier difilter dari backend', function () {
    $user = User::factory()->create();

    Customer::query()->create([
        'name' => 'Budi Aktif',
        'code' => 'CUST-AKTIF',
        'status' => 'aktif',
    ]);
    Customer::query()->create([
        'name' => 'Budi Nonaktif',
        'code' => 'CUST-NONAKTIF',
        'status' => 'nonaktif',
    ]);
    Supplier::query()->create([
        'name' => 'Sumber Aktif',
        'code' => 'SUP-AKTIF',
        'status' => 'aktif',
    ]);
    Supplier::query()->create([
        'name' => 'Sumber Nonaktif',
        'code' => 'SUP-NONAKTIF',
        'status' => 'nonaktif',
    ]);

    $this->actingAs($user)
        ->get(route('customers', ['search' => 'budi', 'status' => 'aktif']))
        ->assertOk()
        ->assertSee('Budi Aktif')
        ->assertDontSee('Budi Nonaktif');

    $this->actingAs($user)
        ->get(route('suppliers', ['search' => 'sumber', 'status' => 'nonaktif']))
        ->assertOk()
        ->assertSee('Sumber Nonaktif')
        ->assertDontSee('Sumber Aktif');
});
