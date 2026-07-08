<?php

use App\Models\Billing;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RawMaterial;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('guest melihat landing page saat membuka dashboard', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Satu Aplikasi Kasir untuk')
        ->assertSee('Mulai Coba Gratis 14 Hari');
});

test('halaman masuk dan daftar dapat dibuka guest', function () {
    $this->get('/signin')
        ->assertOk()
        ->assertSee('Masuk')
        ->assertSee('Masukkan email dan kata sandi untuk masuk.')
        ->assertSee(route('auth.google.redirect'), false);

    $this->get('/signup')
        ->assertOk()
        ->assertSee('Daftar')
        ->assertSee('Buat akun baru untuk mengakses dashboard.')
        ->assertSee(route('auth.google.redirect'), false);
});

test('halaman legal publik dapat dibuka guest', function () {
    $this->get('/privacy-policy')
        ->assertOk()
        ->assertSee('Kebijakan Privasi MavaPOS')
        ->assertSee('Data yang Kami Kumpulkan');

    $this->get('/terms-of-service')
        ->assertOk()
        ->assertSee('Syarat dan Ketentuan Layanan MavaPOS')
        ->assertSee('Penerimaan Ketentuan');
});

test('pengguna dapat daftar lalu masuk ke dashboard', function () {
    $response = $this->post('/signup', [
        'first_name' => 'Budi',
        'last_name' => 'Santoso',
        'email' => 'budi@example.com',
        'password' => 'password123',
        'terms' => '1',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
    ]);
});

test('pengguna dapat masuk dan keluar', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password123',
    ]);

    $this->post('/signin', [
        'email' => 'admin@example.com',
        'password' => 'password123',
    ])->assertRedirect('/');

    $this->assertAuthenticated();

    $this->post('/logout')
        ->assertRedirect('/signin');

    $this->assertGuest();
});

test('seed test user menggunakan role owner', function () {
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'role' => 'owner',
    ]);
});

test('halaman 403 tampil ramah saat role tidak memiliki akses', function () {
    $cashier = User::factory()->create([
        'role' => 'kasir',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->actingAs($cashier)
        ->get('/settings')
        ->assertForbidden()
        ->assertSee('Akses dibatasi')
        ->assertSee('Role akun Anda belum memiliki izin')
        ->assertSee('Kembali ke Dashboard');
});

test('dashboard toko menampilkan ringkasan dan grafik berbahasa indonesia', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Ringkasan Toko')
        ->assertSee('Total Produk')
        ->assertSee('Penjualan Hari Ini')
        ->assertSee('Pendapatan Bulan Ini')
        ->assertSee('Grafik Penjualan')
        ->assertSee('Grafik Pendapatan')
        ->assertSee('Top 5 Barang Dibeli')
        ->assertDontSee('Monthly Target')
        ->assertDontSee('Statistics')
        ->assertDontSee('Customers Demographic')
        ->assertDontSee('Recent Orders');
});

test('dashboard toko menyinkronkan grafik dan ringkasan dari database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-SKU-001', 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => 18000,
        ])
        ->assertOk();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Rp18.000')
        ->assertSee('Kopi Susu Aren 250ml')
        ->assertSee('data-chart-series', false)
        ->assertSee('data-chart-revenue', false)
        ->assertSee('18000', false);
});

test('notification header menampilkan aktivitas sistem dari database', function () {
    $user = User::factory()->create(['name' => 'Kasir Notifikasi']);
    StoreSetting::current()->update([
        'store_name' => 'Mava Logo Mart',
        'logo_path' => 'store-logos/logo-test.png',
    ]);

    $this->actingAs($user)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $checkout = $this->actingAs($user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-SKU-001', 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => 18000,
        ])
        ->assertOk();

    $invoice = $checkout->json('sale.invoice_number');
    expect($checkout->json('sale.store.name'))->toBe('Mava Logo Mart')
        ->and($checkout->json('sale.store.logo_url'))->toBe('/storage/store-logos/logo-test.png');

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Aktivitas Sistem')
        ->assertSee('Transaksi '.$invoice.' selesai')
        ->assertSee('Kasir Notifikasi menerima Rp18.000')
        ->assertSee('Shift Kasir Notifikasi dimulai')
        ->assertDontSee('Terry Franci')
        ->assertDontSee('Project - Nganter App');
});

test('notification dropdown tetap aman saat dirender tanpa data class component', function () {
    $html = view('components.header.notification-dropdown')->render();

    expect($html)->toContain('Aktivitas Sistem')
        ->and($html)->toContain('Semua aktivitas terbaru sudah normal');
});

test('pengguna dapat update produk melalui controller', function () {
    $user = User::factory()->create();

    $this->post('/signin', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->patchJson('/products/SKU-001', [
            'name' => 'Kopi Susu Aren 300ml',
            'sku' => 'SKU-001-A',
            'category' => 'minuman',
            'barcode' => '899001',
            'buyPrice' => 12000,
            'sellPrice' => 20000,
            'stock' => 8,
            'minStock' => 10,
            'description' => 'Produk hasil update.',
        ])
        ->assertOk()
        ->assertJsonPath('product.name', 'Kopi Susu Aren 300ml')
        ->assertJsonPath('product.sku', 'SKU-001-A')
        ->assertJsonPath('product.category', 'Minuman')
        ->assertJsonPath('product.price', 'Rp20.000')
        ->assertJsonPath('product.buyPrice', 'Rp12.000')
        ->assertJsonPath('product.status', 'Stok Menipis');

    $this->assertDatabaseHas('products', [
        'name' => 'Kopi Susu Aren 300ml',
        'sku' => 'SKU-001-A',
        'barcode' => '899001',
        'sell_price' => 20000,
        'stock' => 8,
        'min_stock' => 10,
    ]);
});

test('pengguna dapat membuat produk dan tersimpan ke database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/products', [
            'name' => 'Teh Melati 350ml',
            'sku' => 'SKU-088',
            'category' => 'minuman',
            'barcode' => '899088',
            'buyPrice' => 7000,
            'sellPrice' => 10000,
            'stock' => 40,
            'minStock' => 5,
            'description' => 'Produk baru.',
        ])
        ->assertCreated()
        ->assertJsonPath('product.name', 'Teh Melati 350ml')
        ->assertJsonPath('product.category', 'Minuman')
        ->assertJsonPath('product.price', 'Rp10.000');

    $this->assertDatabaseHas('products', [
        'name' => 'Teh Melati 350ml',
        'sku' => 'SKU-088',
        'buy_price' => 7000,
        'sell_price' => 10000,
    ]);
});

test('pengguna dapat menghapus produk beserta data stok cabang dan varian', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/products', [
            'name' => 'Produk Hapus',
            'sku' => 'DEL-001',
            'category' => 'minuman',
            'sellPrice' => 15000,
            'stock' => 12,
            'variants' => [
                [
                    'name' => 'Produk Hapus Varian',
                    'sku' => 'DEL-001-VAR',
                    'sellPrice' => 17000,
                    'stock' => 5,
                ],
            ],
        ])
        ->assertCreated();

    $product = Product::query()->where('sku', 'DEL-001')->firstOrFail();
    $variant = $product->variants()->where('sku', 'DEL-001-VAR')->firstOrFail();

    $this->assertDatabaseHas('branch_inventories', [
        'product_id' => $product->id,
    ]);
    $this->assertDatabaseHas('branch_inventories', [
        'product_variant_id' => $variant->id,
    ]);

    $this->actingAs($user)
        ->deleteJson('/products/DEL-001')
        ->assertOk()
        ->assertJsonPath('message', 'Produk DEL-001 berhasil dihapus.');

    $this->assertDatabaseMissing('products', [
        'sku' => 'DEL-001',
    ]);
    $this->assertDatabaseMissing('product_variants', [
        'sku' => 'DEL-001-VAR',
    ]);
    $this->assertDatabaseMissing('branch_inventories', [
        'product_id' => $product->id,
    ]);
    $this->assertDatabaseMissing('branch_inventories', [
        'product_variant_id' => $variant->id,
    ]);
});

test('pengguna dapat membuat produk dengan varian dan tersimpan ke database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/products', [
            'name' => 'Nasi Goreng Spesial',
            'sku' => 'FNB-001',
            'category' => 'makanan',
            'barcode' => '8991001',
            'buyPrice' => 12000,
            'sellPrice' => 22000,
            'stock' => 25,
            'minStock' => 5,
            'description' => 'Produk F&B dengan pilihan varian.',
            'variants' => [
                [
                    'name' => 'Regular Pedas',
                    'sku' => 'FNB-001-REG-PDS',
                    'barcode' => '899100101',
                    'unit' => 'porsi',
                    'attributes' => [
                        'ukuran' => 'Regular',
                        'level_pedas' => 'Pedas',
                        'catatan_dapur' => 'Tanpa acar',
                    ],
                    'buyPrice' => 12000,
                    'sellPrice' => 22000,
                    'stock' => 15,
                    'minStock' => 3,
                    'isActive' => true,
                    'isFavorite' => true,
                    'isTaxable' => true,
                    'isDiscountable' => false,
                    'servingTimeMinutes' => 12,
                    'availableForDineIn' => true,
                    'availableForTakeaway' => true,
                ],
                [
                    'name' => 'Large Tidak Pedas',
                    'sku' => 'FNB-001-LRG-NP',
                    'unit' => 'porsi',
                    'attributes' => [
                        'ukuran' => 'Large',
                        'level_pedas' => 'Tidak pedas',
                        'topping' => 'Telur',
                    ],
                    'buyPrice' => 15000,
                    'sellPrice' => 28000,
                    'stock' => 10,
                    'minStock' => 2,
                    'isActive' => true,
                    'isFavorite' => false,
                    'isTaxable' => true,
                    'isDiscountable' => true,
                    'servingTimeMinutes' => 15,
                    'availableForDineIn' => true,
                    'availableForTakeaway' => false,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('product.sku', 'FNB-001')
        ->assertJsonPath('product.variantCount', 2)
        ->assertJsonPath('product.variants.0.sku', 'FNB-001-REG-PDS')
        ->assertJsonPath('product.variants.0.attributes.level_pedas', 'Pedas')
        ->assertJsonPath('product.variants.1.availableForTakeaway', false);

    $this->assertDatabaseHas('products', [
        'sku' => 'FNB-001',
        'name' => 'Nasi Goreng Spesial',
    ]);

    $this->assertDatabaseHas('product_variants', [
        'sku' => 'FNB-001-REG-PDS',
        'name' => 'Regular Pedas',
        'unit' => 'porsi',
        'sell_price' => 22000,
        'stock' => 15,
        'is_favorite' => true,
        'is_discountable' => false,
        'serving_time_minutes' => 12,
        'available_for_takeaway' => true,
    ]);

    $this->assertDatabaseHas('product_variants', [
        'sku' => 'FNB-001-LRG-NP',
        'name' => 'Large Tidak Pedas',
        'sell_price' => 28000,
        'available_for_takeaway' => false,
    ]);
});

test('pengguna dapat memperbarui varian produk dan varian lama diganti', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/products', [
            'name' => 'Kopi Literan',
            'sku' => 'GRO-001',
            'category' => 'minuman',
            'sellPrice' => 75000,
            'variants' => [
                [
                    'name' => 'Botol 1 Liter',
                    'sku' => 'GRO-001-1L',
                    'unit' => 'liter',
                    'sellPrice' => 75000,
                    'stock' => 20,
                ],
            ],
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->patchJson('/products/GRO-001', [
            'name' => 'Kopi Literan Premium',
            'sku' => 'GRO-001',
            'category' => 'minuman',
            'sellPrice' => 90000,
            'stock' => 30,
            'variants' => [
                [
                    'name' => 'Dus 6 Botol',
                    'sku' => 'GRO-001-DUS6',
                    'unit' => 'box',
                    'unitConversion' => 6,
                    'sellPrice' => 510000,
                    'stock' => 5,
                    'attributes' => [
                        'kemasan' => 'Dus',
                        'isi' => '6 botol',
                    ],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('product.variantCount', 1)
        ->assertJsonPath('product.variants.0.sku', 'GRO-001-DUS6')
        ->assertJsonPath('product.variants.0.unitConversion', 6);

    $this->assertDatabaseMissing('product_variants', [
        'sku' => 'GRO-001-1L',
    ]);

    $this->assertDatabaseHas('product_variants', [
        'sku' => 'GRO-001-DUS6',
        'unit' => 'box',
        'unit_conversion' => 6,
        'sell_price' => 510000,
    ]);
});

test('pengguna dapat membuka halaman resep produk', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/product-recipes')
        ->assertOk()
        ->assertSee('Resep Produk')
        ->assertSee('Atur Resep')
        ->assertSee('Daftar Resep');
});

test('pengguna dapat membuka halaman inventory bahan baku', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/raw-materials')
        ->assertOk()
        ->assertSee('Inventory Bahan Baku')
        ->assertSee('Tambah Bahan Baku')
        ->assertSee('Daftar Bahan Baku');
});

test('pengguna dapat mencatat bahan baku inventory', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/raw-materials', [
            'code' => 'BB-KOPI',
            'name' => 'Kopi bubuk',
            'category' => 'Bahan minuman',
            'unit' => 'gram',
            'stock' => 500,
            'min_stock' => 100,
            'cost_per_unit' => 120,
        ])
        ->assertRedirect('/raw-materials');

    $this->assertDatabaseHas('raw_materials', [
        'code' => 'BB-KOPI',
        'name' => 'Kopi bubuk',
        'unit' => 'gram',
        'cost_per_unit' => 120,
    ]);
});

test('pengguna dapat menyimpan standar bahan resep produk', function () {
    $user = User::factory()->create();
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $coffee = RawMaterial::query()->create([
        'code' => 'BB-KOPI',
        'name' => 'Kopi bubuk',
        'category' => 'Bahan minuman',
        'unit' => 'gram',
        'stock' => 500,
        'min_stock' => 100,
        'cost_per_unit' => 120,
    ]);
    $palmSugar = RawMaterial::query()->create([
        'code' => 'BB-GULA-AREN',
        'name' => 'Gula aren',
        'category' => 'Bahan minuman',
        'unit' => 'ml',
        'stock' => 1000,
        'min_stock' => 200,
        'cost_per_unit' => 80,
    ]);

    $this->actingAs($user)
        ->post('/product-recipes', [
            'product_id' => $product->id,
            'items' => [
                [
                    'raw_material_id' => $coffee->id,
                    'quantity' => 18,
                ],
                [
                    'raw_material_id' => $palmSugar->id,
                    'quantity' => 25,
                ],
            ],
        ])
        ->assertRedirect('/product-recipes');

    $this->assertDatabaseHas('product_recipe_items', [
        'product_id' => $product->id,
        'raw_material_id' => $coffee->id,
        'item_name' => 'Kopi bubuk',
        'quantity' => 18,
        'unit' => 'gram',
    ]);

    $this->assertDatabaseHas('product_recipe_items', [
        'product_id' => $product->id,
        'raw_material_id' => $palmSugar->id,
        'item_name' => 'Gula aren',
        'quantity' => 25,
        'unit' => 'ml',
    ]);
});

test('pengguna dapat membuka halaman stok', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/inventory')
        ->assertOk()
        ->assertSee('Stok')
        ->assertSee('Total:')
        ->assertSee('produk')
        ->assertSee('Stok Menipis');
});

test('pengguna dapat membuka halaman kasir', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/pos')
        ->assertOk()
        ->assertSee('Kasir')
        ->assertSee('Keranjang')
        ->assertDontSee('Metode Printer')
        ->assertSee('Selesaikan Pembayaran')
        ->assertDontSee('Cari atau ketik perintah');
});

test('pengguna dapat membuka halaman laporan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/reports')
        ->assertOk()
        ->assertSee('Laporan')
        ->assertSee('Nilai Stok Jual')
        ->assertSee('Estimasi Laba/Rugi')
        ->assertSee('Total Pengeluaran')
        ->assertSee('Unduh PDF')
        ->assertSee('Produk Stok Terbesar');
});

test('pengguna dapat membuka halaman test printing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/print-test')
        ->assertOk()
        ->assertSee('Test Printing')
        ->assertSee('Service UUID')
        ->assertSee('IMIN InnerPrinter')
        ->assertSee('127.0.0.1:8081');
});

test('role membatasi akses halaman sesuai tugas user', function () {
    $cashier = User::factory()->create([
        'role' => 'kasir',
        'trial_ends_at' => now()->addDays(7),
    ]);
    $warehouse = User::factory()->create([
        'role' => 'gudang',
        'trial_ends_at' => now()->addDays(7),
    ]);

    $this->actingAs($cashier)
        ->get('/pos')
        ->assertOk();

    $this->actingAs($cashier)
        ->get('/settings')
        ->assertForbidden();

    $this->actingAs($warehouse)
        ->get('/inventory')
        ->assertOk();

    $this->actingAs($warehouse)
        ->get('/pos')
        ->assertForbidden();
});

test('subscription gate memblokir fitur operasional saat trial dan langganan berakhir', function () {
    $owner = User::factory()->create([
        'role' => 'owner',
        'trial_ends_at' => now()->subDay(),
    ]);

    $this->actingAs($owner)
        ->get('/pos')
        ->assertRedirect('/billings');

    $this->actingAs($owner)
        ->get('/billings')
        ->assertOk()
        ->assertSee('Buat Tagihan Langganan');

    $cashier = User::factory()->create([
        'role' => 'kasir',
        'trial_ends_at' => null,
    ]);

    $this->actingAs($cashier)
        ->get('/pos')
        ->assertStatus(402)
        ->assertSee('Masa trial atau langganan sudah berakhir');
});

test('pengguna dapat membuka halaman pengeluaran', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/expenses')
        ->assertOk()
        ->assertSee('Pengeluaran')
        ->assertSee('Catat Pengeluaran')
        ->assertSee('Riwayat Pengeluaran');
});

test('pengeluaran stok menambah stok produk dan mencatat mutasi', function () {
    $user = User::factory()->create();
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $stockBefore = $product->stock;

    $this->actingAs($user)
        ->post('/expenses', [
            'type' => 'stock',
            'title' => 'Belanja Kopi Susu Aren',
            'category' => 'Bahan utama',
            'product_id' => $product->id,
            'quantity' => 12,
            'unit_cost' => 9000,
            'amount' => 108000,
            'reference' => 'NOTA-EXP-001',
            'spent_at' => now()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect('/expenses');

    expect($product->fresh()->stock)->toBe($stockBefore + 12);

    $this->assertDatabaseHas('expenses', [
        'type' => 'stock',
        'title' => 'Belanja Kopi Susu Aren',
        'amount' => 108000,
        'quantity' => 12,
        'product_id' => $product->id,
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'type' => 'in',
        'quantity' => 12,
        'stock_before' => $stockBefore,
        'stock_after' => $stockBefore + 12,
        'reference' => 'NOTA-EXP-001',
    ]);
});

test('pengguna dapat mengunduh laporan sebagai pdf', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/reports/download');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

test('pengguna dapat update stok produk dan tersimpan ke database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/inventory/SKU-001', [
            'stock' => 30,
            'minStock' => 5,
        ])
        ->assertOk()
        ->assertJsonPath('item.sku', 'SKU-001')
        ->assertJsonPath('item.stock', 30)
        ->assertJsonPath('item.minStock', 5)
        ->assertJsonPath('item.status', 'Aktif');

    $this->assertDatabaseHas('products', [
        'sku' => 'SKU-001',
        'stock' => 30,
        'min_stock' => 5,
    ]);
});

test('pengguna dapat mencatat stok masuk dan stok produk bertambah', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/inventory/SKU-001/in', [
            'quantity' => 12,
            'reference' => 'PO-001',
            'note' => 'Restok pemasok',
        ])
        ->assertCreated()
        ->assertJsonPath('item.sku', 'SKU-001')
        ->assertJsonPath('item.stock', 140)
        ->assertJsonPath('movement.type', 'Masuk')
        ->assertJsonPath('movement.quantity', 12)
        ->assertJsonPath('movement.stockBefore', 128)
        ->assertJsonPath('movement.stockAfter', 140);

    $this->assertDatabaseHas('products', [
        'sku' => 'SKU-001',
        'stock' => 140,
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'type' => 'in',
        'quantity' => 12,
        'stock_before' => 128,
        'stock_after' => 140,
        'reference' => 'PO-001',
    ]);
});

test('pengguna dapat mencatat stok keluar dan stok produk berkurang', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/inventory/SKU-001/out', [
            'quantity' => 8,
            'reference' => 'SO-001',
            'note' => 'Penjualan toko',
        ])
        ->assertCreated()
        ->assertJsonPath('item.sku', 'SKU-001')
        ->assertJsonPath('item.stock', 120)
        ->assertJsonPath('movement.type', 'Keluar')
        ->assertJsonPath('movement.quantity', 8)
        ->assertJsonPath('movement.stockBefore', 128)
        ->assertJsonPath('movement.stockAfter', 120);

    $this->assertDatabaseHas('products', [
        'sku' => 'SKU-001',
        'stock' => 120,
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'type' => 'out',
        'quantity' => 8,
        'stock_before' => 128,
        'stock_after' => 120,
        'reference' => 'SO-001',
    ]);
});

test('stok keluar tidak boleh melebihi stok tersedia', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/inventory/SKU-037/out', [
            'quantity' => 1,
            'reference' => 'SO-VOID',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Stok keluar melebihi stok tersedia.');

    $this->assertDatabaseHas('products', [
        'sku' => 'SKU-037',
        'stock' => 0,
    ]);

    $this->assertDatabaseMissing('stock_movements', [
        'type' => 'out',
        'quantity' => 1,
        'reference' => 'SO-VOID',
    ]);
});

test('pengguna dapat update dan mencatat pergerakan stok varian produk', function () {
    $user = User::factory()->create();
    $product = Product::query()->create([
        'sku' => 'PARENT-SKU',
        'name' => 'Product Parent',
        'sell_price' => 10000,
    ]);
    $variant = \App\Models\ProductVariant::query()->create([
        'product_id' => $product->id,
        'name' => 'Ukuran L',
        'sku' => 'VAR-SKU',
        'sell_price' => 10000,
        'is_active' => true,
    ]);
    $variantWithoutSku = \App\Models\ProductVariant::query()->create([
        'product_id' => $product->id,
        'name' => 'Ukuran XL',
        'sku' => null,
        'sell_price' => 12000,
        'is_active' => true,
    ]);

    $branchId = app(\App\Support\BranchContext::class)->activeId();
    app(\App\Support\BranchInventoryManager::class)->initializeBranch($branchId);

    // Update variant stock with custom SKU
    $this->actingAs($user)
        ->patchJson('/inventory/VAR-SKU', [
            'stock' => 50,
            'minStock' => 5,
        ])
        ->assertOk()
        ->assertJsonPath('item.variants.0.stock', 50)
        ->assertJsonPath('item.variants.0.minStock', 5);

    // Update variant stock with fallback SKU (PARENT-SKU-[id])
    $fallbackSku = 'PARENT-SKU-' . $variantWithoutSku->id;
    $this->actingAs($user)
        ->patchJson('/inventory/' . $fallbackSku, [
            'stock' => 80,
            'minStock' => 8,
        ])
        ->assertOk()
        ->assertJsonPath('item.variants.1.stock', 80)
        ->assertJsonPath('item.variants.1.minStock', 8);

    // Record variant stock in
    $this->actingAs($user)
        ->postJson('/inventory/VAR-SKU/in', [
            'quantity' => 10,
            'reference' => 'REF-IN',
            'note' => 'Restock L',
        ])
        ->assertCreated()
        ->assertJsonPath('item.variants.0.stock', 60)
        ->assertJsonPath('movement.sku', 'VAR-SKU')
        ->assertJsonPath('movement.quantity', 10);

    // Record variant stock out using fallback SKU
    $this->actingAs($user)
        ->postJson('/inventory/' . $fallbackSku . '/out', [
            'quantity' => 15,
            'reference' => 'REF-OUT',
            'note' => 'Penjualan XL',
        ])
        ->assertCreated()
        ->assertJsonPath('item.variants.1.stock', 65)
        ->assertJsonPath('movement.sku', $fallbackSku)
        ->assertJsonPath('movement.quantity', 15);
});

test('pengguna dapat membuka halaman kategori produk', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/product-categories')
        ->assertOk()
        ->assertSee('Kategori Produk')
        ->assertSee('Total:')
        ->assertSee('kategori');
});

test('pengguna dapat update kategori produk melalui controller', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/product-categories/minuman', [
            'name' => 'Minuman Dingin',
            'code' => 'minuman-dingin',
            'status' => 'aktif',
            'productCount' => 31,
            'description' => 'Kategori minuman siap konsumsi.',
        ])
        ->assertOk()
        ->assertJsonPath('category.name', 'Minuman Dingin')
        ->assertJsonPath('category.code', 'minuman-dingin')
        ->assertJsonPath('category.status', 'Aktif')
        ->assertJsonPath('category.productCount', 31)
        ->assertJsonPath('category.description', 'Kategori minuman siap konsumsi.');

    $this->assertDatabaseHas('product_categories', [
        'name' => 'Minuman Dingin',
        'code' => 'minuman-dingin',
        'status' => 'aktif',
        'description' => 'Kategori minuman siap konsumsi.',
    ]);
});

test('pengguna dapat membuat kategori produk dan tersimpan ke database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/product-categories', [
            'name' => 'Frozen Food',
            'code' => 'frozen-food',
            'status' => 'aktif',
            'description' => 'Kategori produk beku.',
        ])
        ->assertCreated()
        ->assertJsonPath('category.name', 'Frozen Food')
        ->assertJsonPath('category.code', 'frozen-food');

    $this->assertDatabaseHas('product_categories', [
        'name' => 'Frozen Food',
        'code' => 'frozen-food',
        'status' => 'aktif',
    ]);
});

test('pengguna dapat menghapus kategori produk yang belum digunakan', function () {
    $user = User::factory()->create();
    ProductCategory::query()->create([
        'name' => 'Kategori Hapus',
        'code' => 'kategori-hapus',
        'status' => 'aktif',
        'product_count' => 0,
    ]);

    $this->actingAs($user)
        ->deleteJson('/product-categories/kategori-hapus')
        ->assertOk()
        ->assertJsonPath('message', 'Kategori produk kategori-hapus berhasil dihapus.');

    $this->assertDatabaseMissing('product_categories', [
        'code' => 'kategori-hapus',
    ]);
});

test('kategori produk yang masih digunakan tidak dapat dihapus', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson('/product-categories/minuman')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Kategori produk masih digunakan oleh produk. Pindahkan atau hapus produk terkait terlebih dahulu.');

    $this->assertDatabaseHas('product_categories', [
        'code' => 'minuman',
    ]);
});

test('pengguna dapat membuka halaman supplier', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/suppliers')
        ->assertOk()
        ->assertSee('Supplier')
        ->assertSee('Total:')
        ->assertSee('supplier');
});

test('pengguna dapat membuat supplier dan tersimpan ke database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/suppliers', [
            'name' => 'CV Sumber Makmur',
            'code' => 'SUP-088',
            'phone' => '081234567890',
            'email' => 'sumber@example.com',
            'status' => 'aktif',
            'address' => 'Jl. Niaga No. 10',
        ])
        ->assertCreated()
        ->assertJsonPath('supplier.name', 'CV Sumber Makmur')
        ->assertJsonPath('supplier.code', 'SUP-088')
        ->assertJsonPath('supplier.status', 'Aktif');

    $this->assertDatabaseHas('suppliers', [
        'name' => 'CV Sumber Makmur',
        'code' => 'SUP-088',
        'phone' => '081234567890',
        'email' => 'sumber@example.com',
        'status' => 'aktif',
    ]);
});

test('pengguna dapat update supplier melalui controller', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/suppliers/SUP-001', [
            'name' => 'PT Kopi Nusantara Updated',
            'code' => 'SUP-001-A',
            'phone' => '081111111111',
            'email' => 'kopi-updated@example.com',
            'status' => 'nonaktif',
            'address' => 'Jl. Gudang Baru No. 1',
        ])
        ->assertOk()
        ->assertJsonPath('supplier.name', 'PT Kopi Nusantara Updated')
        ->assertJsonPath('supplier.code', 'SUP-001-A')
        ->assertJsonPath('supplier.status', 'Nonaktif');

    $this->assertDatabaseHas('suppliers', [
        'name' => 'PT Kopi Nusantara Updated',
        'code' => 'SUP-001-A',
        'status' => 'nonaktif',
    ]);
});

test('pengguna dapat membuka halaman pelanggan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/customers')
        ->assertOk()
        ->assertSee('Pelanggan')
        ->assertSee('Total:')
        ->assertSee('pelanggan');
});

test('pengguna dapat membuat tagihan langganan qris pakasir tanpa isi data diri', function () {
    config([
        'services.pakasir.project' => 'mava-test',
        'services.pakasir.api_key' => 'secret-test',
    ]);

    Http::fake([
        'https://app.pakasir.com/api/transactioncreate/qris' => Http::response([
            'payment' => [
                'fee' => 350,
                'total_payment' => 2689550,
                'payment_number' => 'QRIS-CODE',
                'expired_at' => '2026-06-17T14:30:00+08:00',
            ],
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/billings', [
            'plan_slug' => 'plus',
            'billing_cycle' => 'yearly',
        ])
        ->assertRedirect('/billings');

    $billing = Billing::query()->first();

    expect($billing)
        ->not->toBeNull()
        ->and($billing->customer_name)->toBe('Mava Mart')
        ->and($billing->customer_phone)->toBe('081234567890')
        ->and($billing->title)->toBe('Plus Plan - Tahunan')
        ->and($billing->amount)->toBe(2689200)
        ->and($billing->fee)->toBe(350)
        ->and($billing->total_payment)->toBe(2689550)
        ->and($billing->payment_status)->toBe('pending')
        ->and($billing->provider_payload['subscription']['billing_cycle'])->toBe('yearly')
        ->and($billing->provider_payload['subscription']['yearly_discount_percent'])->toBe(10);

    Http::assertSent(fn ($request) => $request->url() === 'https://app.pakasir.com/api/transactioncreate/qris'
        && $request['project'] === 'mava-test'
        && $request['order_id'] === $billing->invoice_number
        && $request['amount'] === 2689200
        && $request['api_key'] === 'secret-test');
});

test('form buat tagihan langganan hanya muncul pada masa renewal h-7 atau subscription tidak aktif', function () {
    Carbon::setTestNow('2026-06-21 10:00:00');

    $user = User::factory()->create();

    Billing::query()->create([
        'invoice_number' => 'INV-AKTIF-JAUH',
        'customer_name' => 'Mava Mart',
        'title' => 'Plus Plan - Bulanan',
        'amount' => 249000,
        'payment_status' => 'completed',
        'paid_at' => now()->subDay(),
        'provider_payload' => [
            'subscription' => [
                'plan_slug' => 'plus',
                'plan_name' => 'Plus Plan',
                'billing_cycle' => 'monthly',
                'billing_cycle_label' => 'Bulanan',
                'period_starts_at' => '2026-06-20',
                'period_ends_at' => '2026-07-10',
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get('/billings')
        ->assertOk()
        ->assertSee('Tagihan Langganan Belum Tersedia')
        ->assertDontSee('Buat Tagihan Langganan');

    Billing::query()->delete();

    Billing::query()->create([
        'invoice_number' => 'INV-AKTIF-H7',
        'customer_name' => 'Mava Mart',
        'title' => 'Plus Plan - Bulanan',
        'amount' => 249000,
        'payment_status' => 'completed',
        'paid_at' => now()->subDay(),
        'provider_payload' => [
            'subscription' => [
                'plan_slug' => 'plus',
                'plan_name' => 'Plus Plan',
                'billing_cycle' => 'monthly',
                'billing_cycle_label' => 'Bulanan',
                'period_starts_at' => '2026-06-01',
                'period_ends_at' => '2026-06-28',
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get('/billings')
        ->assertOk()
        ->assertSee('Buat Tagihan Langganan')
        ->assertDontSee('Tagihan Langganan Belum Tersedia');

    Billing::query()->delete();

    $this->actingAs($user)
        ->get('/billings')
        ->assertOk()
        ->assertSee('Buat Tagihan Langganan');

    Carbon::setTestNow();
});

test('webhook pakasir menandai tagihan lunas setelah verifikasi detail', function () {
    config([
        'services.pakasir.project' => 'mava-test',
        'services.pakasir.api_key' => 'secret-test',
    ]);

    $billing = Billing::query()->create([
        'invoice_number' => 'INV-TEST-001',
        'customer_name' => 'Pelanggan QRIS',
        'title' => 'Pembayaran Order',
        'amount' => 75000,
        'payment_status' => 'pending',
    ]);

    Http::fake([
        'https://app.pakasir.com/api/transactiondetail*' => Http::response([
            'transaction' => [
                'project' => 'mava-test',
                'order_id' => $billing->invoice_number,
                'amount' => 75000,
                'status' => 'completed',
                'payment_method' => 'qris',
                'completed_at' => '2026-06-17T15:00:00+08:00',
            ],
        ]),
    ]);

    $this->postJson('/pakasir/webhook', [
        'project' => 'mava-test',
        'order_id' => $billing->invoice_number,
        'amount' => 75000,
        'status' => 'completed',
        'payment_method' => 'qris',
        'completed_at' => '2026-06-17T15:00:00+08:00',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('invoice_number', 'INV-TEST-001')
        ->assertJsonPath('payment_status', 'completed');

    $billing->refresh();

    expect($billing->payment_status)->toBe('completed')
        ->and($billing->paid_at)->not->toBeNull();
});

test('webhook pakasir menerima status paid sebagai pembayaran berhasil', function () {
    config([
        'services.pakasir.project' => 'mava-test',
        'services.pakasir.api_key' => 'secret-test',
    ]);

    $billing = Billing::query()->create([
        'invoice_number' => 'INV-TEST-PAID',
        'customer_name' => 'Mava Mart',
        'title' => 'Plus Plan - Bulanan',
        'amount' => 249000,
        'payment_status' => 'pending',
    ]);

    Http::fake([
        'https://app.pakasir.com/api/transactiondetail*' => Http::response([
            'transaction' => [
                'project' => 'mava-test',
                'order_id' => $billing->invoice_number,
                'amount' => 249000,
                'status' => 'paid',
                'payment_method' => 'qris',
                'paid_at' => '2026-06-17T15:00:00+08:00',
            ],
        ]),
    ]);

    $this->postJson('/pakasir/webhook', [
        'project' => 'mava-test',
        'order_id' => $billing->invoice_number,
        'amount' => 249000,
        'status' => 'paid',
        'payment_method' => 'qris',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('invoice_number', 'INV-TEST-PAID')
        ->assertJsonPath('payment_status', 'completed');

    $billing->refresh();

    expect($billing->payment_status)->toBe('completed')
        ->and($billing->paid_at)->not->toBeNull()
        ->and($billing->provider_payload['verified_detail'])->not->toBeEmpty();
});

test('pengguna dapat membuat pelanggan dan tersimpan ke database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/customers', [
            'name' => 'Rina Wijaya',
            'code' => 'CUST-088',
            'phone' => '082233445566',
            'email' => 'rina@example.com',
            'status' => 'aktif',
            'address' => 'Jl. Pelanggan No. 8',
        ])
        ->assertCreated()
        ->assertJsonPath('customer.name', 'Rina Wijaya')
        ->assertJsonPath('customer.code', 'CUST-088')
        ->assertJsonPath('customer.status', 'Aktif');

    $this->assertDatabaseHas('customers', [
        'name' => 'Rina Wijaya',
        'code' => 'CUST-088',
        'phone' => '082233445566',
        'email' => 'rina@example.com',
        'status' => 'aktif',
    ]);
});

test('pengguna dapat update pelanggan melalui controller', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/customers/CUST-001', [
            'name' => 'Budi Santoso Updated',
            'code' => 'CUST-001-A',
            'phone' => '083344556677',
            'email' => 'budi-updated@example.com',
            'status' => 'nonaktif',
            'address' => 'Jl. Loyalti Baru No. 1',
        ])
        ->assertOk()
        ->assertJsonPath('customer.name', 'Budi Santoso Updated')
        ->assertJsonPath('customer.code', 'CUST-001-A')
        ->assertJsonPath('customer.status', 'Nonaktif');

    $this->assertDatabaseHas('customers', [
        'name' => 'Budi Santoso Updated',
        'code' => 'CUST-001-A',
        'status' => 'nonaktif',
    ]);
});

test('pengguna dapat membuka halaman pengaturan toko', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings')
        ->assertOk()
        ->assertSee('Pengaturan')
        ->assertSee('Pengaturan Dasar')
        ->assertSee('Pengaturan Produk')
        ->assertSee('Nama Bisnis')
        ->assertSee('Tagline')
        ->assertSee('Nama Akun Instagram')
        ->assertDontSee('Tampilkan Logo')
        ->assertSee('Tipe Bisnis')
        ->assertSee('Mata Uang')
        ->assertSee('Mava Mart')
        ->assertSee('Satuan Produk')
        ->assertSee('Modifier/Add-on')
        ->assertSee('Level Pedas')
        ->assertSee('Catatan Dapur');
});

test('pengguna dapat mengganti logo toko dengan file svg', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'owner']);
    $svg = UploadedFile::fake()->create('logo.svg', 12, 'image/svg+xml');

    $this->actingAs($user)
        ->patch('/settings', [
            'store_name' => 'Mava Mart SVG',
            'business_type' => 'retail',
            'currency' => 'IDR',
            'logo' => $svg,
        ])
        ->assertRedirect('/settings')
        ->assertSessionHas('status', 'Pengaturan toko berhasil disimpan.');

    $setting = StoreSetting::current();

    expect($setting->logo_path)->toStartWith('store-logos/')
        ->and($setting->logo_path)->toEndWith('.svg');

    Storage::disk('public')->assertExists($setting->logo_path);
});

test('pengguna dapat update identitas toko dan tersimpan ke database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings', [
            'store_name' => 'Mava Mart Pusat',
            'tagline' => 'Belanja hemat setiap hari',
            'business_type' => 'retail',
            'currency' => 'IDR',
            'legal_name' => 'PT Mava Retail Indonesia',
            'owner_name' => 'Andi Wijaya',
            'address' => 'Jl. Sudirman No. 10, Makassar',
            'phone' => '0411123456',
            'whatsapp' => '081234567890',
            'email' => 'halo@mavamart.test',
            'website' => 'https://mavamart.test',
            'instagram' => '@mavamart',
            'facebook' => 'Mava Mart',
            'tiktok' => '@mavamart',
            'tax_number' => '09.123.456.7-801.000',
            'operational_hours' => 'Senin-Minggu 08.00-22.00',
            'notes' => 'Buka setiap hari pukul 08.00-22.00.',
        ])
        ->assertRedirect('/settings')
        ->assertSessionHas('status', 'Pengaturan toko berhasil disimpan.');

    $this->assertDatabaseHas('store_settings', [
        'store_name' => 'Mava Mart Pusat',
        'tagline' => 'Belanja hemat setiap hari',
        'business_type' => 'retail',
        'currency' => 'IDR',
        'legal_name' => 'PT Mava Retail Indonesia',
        'owner_name' => 'Andi Wijaya',
        'address' => 'Jl. Sudirman No. 10, Makassar',
        'phone' => '0411123456',
        'whatsapp' => '081234567890',
        'email' => 'halo@mavamart.test',
        'website' => 'https://mavamart.test',
        'instagram' => '@mavamart',
        'facebook' => 'Mava Mart',
        'tiktok' => '@mavamart',
        'tax_number' => '09.123.456.7-801.000',
        'operational_hours' => 'Senin-Minggu 08.00-22.00',
    ]);

    $this->actingAs($user)
        ->get('/settings')
        ->assertOk()
        ->assertSee('Mava Mart Pusat')
        ->assertSee('Belanja hemat setiap hari')
        ->assertSee('@mavamart');
});

test('pengguna dapat update pengaturan produk global dan tersimpan ke database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings', [
            'store_name' => 'Mava Mart',
            'business_type' => 'cafe',
            'currency' => 'IDR',
            'product_categories' => 'Minuman, Makanan, Dessert',
            'product_units' => 'pcs, box, kg, gram, liter, porsi',
            'product_brands' => 'Mava Kitchen, Kopi Nusantara',
            'product_variants' => 'Ukuran, Warna, Rasa',
            'product_modifiers' => 'Extra shot, Keju, Saus',
            'sku_mode' => 'auto',
            'barcode_enabled' => '1',
            'selling_price_enabled' => '1',
            'cost_price_enabled' => '1',
            'product_status_enabled' => '1',
            'cashier_favorite_enabled' => '1',
            'taxable_default' => '1',
            'discountable_default' => '1',
            'spicy_levels' => 'Tidak pedas, Sedang, Pedas',
            'toppings' => 'Keju, Boba, Telur',
            'size_options' => 'Regular, Large',
            'kitchen_notes_enabled' => '1',
            'dine_in_takeaway_enabled' => '1',
            'serving_time_enabled' => '1',
        ])
        ->assertRedirect('/settings')
        ->assertSessionHas('status', 'Pengaturan toko berhasil disimpan.');

    $this->assertDatabaseHas('store_settings', [
        'store_name' => 'Mava Mart',
        'business_type' => 'cafe',
        'currency' => 'IDR',
        'product_categories' => 'Minuman, Makanan, Dessert',
        'product_units' => 'pcs, box, kg, gram, liter, porsi',
        'product_brands' => 'Mava Kitchen, Kopi Nusantara',
        'product_variants' => 'Ukuran, Warna, Rasa',
        'product_modifiers' => 'Extra shot, Keju, Saus',
        'sku_mode' => 'auto',
        'barcode_enabled' => true,
        'taxable_default' => true,
        'discountable_default' => true,
        'spicy_levels' => 'Tidak pedas, Sedang, Pedas',
        'toppings' => 'Keju, Boba, Telur',
        'size_options' => 'Regular, Large',
        'kitchen_notes_enabled' => true,
        'dine_in_takeaway_enabled' => true,
        'serving_time_enabled' => true,
    ]);
});

test('pengguna dapat update pengaturan struk dan printer', function () {
    $user = User::factory()->create(['role' => 'owner']);

    $this->actingAs($user)
        ->patch('/settings', [
            'store_name' => 'Mava Mart',
            'business_type' => 'retail',
            'currency' => 'IDR',
            'receipt_footer_note' => 'Terima kasih sudah berbelanja.',
            'receipt_paper_width' => '58',
            'receipt_show_logo' => '1',
            'receipt_show_store_address' => '1',
            'receipt_show_cashier' => '1',
            'printer_auto_print' => '1',
            'printer_close_after_print' => '1',
            'printer_connection_mode' => 'bluetooth',
            'printer_bluetooth_service_uuid' => '000018f0-0000-1000-8000-00805f9b34fb',
            'printer_bluetooth_characteristic_uuid' => '00002af1-0000-1000-8000-00805f9b34fb',
        ])
        ->assertRedirect('/settings')
        ->assertSessionHas('status', 'Pengaturan toko berhasil disimpan.');

    $this->assertDatabaseHas('store_settings', [
        'store_name' => 'Mava Mart',
        'receipt_footer_note' => 'Terima kasih sudah berbelanja.',
        'receipt_paper_width' => '58',
        'receipt_show_logo' => true,
        'receipt_show_store_address' => true,
        'receipt_show_cashier' => true,
        'printer_auto_print' => true,
        'printer_close_after_print' => true,
        'printer_connection_mode' => 'bluetooth',
        'printer_bluetooth_service_uuid' => '000018f0-0000-1000-8000-00805f9b34fb',
        'printer_bluetooth_characteristic_uuid' => '00002af1-0000-1000-8000-00805f9b34fb',
    ]);
});

test('pengguna dapat memilih mode printer imin inner printer', function () {
    $user = User::factory()->create(['role' => 'owner']);

    $this->actingAs($user)
        ->get('/settings')
        ->assertOk()
        ->assertSee('IMIN InnerPrinter');

    $this->actingAs($user)
        ->patch('/settings', [
            'store_name' => 'Mava Mart',
            'business_type' => 'retail',
            'currency' => 'IDR',
            'receipt_paper_width' => '58',
            'printer_connection_mode' => 'imin_inner_printer',
        ])
        ->assertRedirect('/settings')
        ->assertSessionHas('status', 'Pengaturan toko berhasil disimpan.');

    $this->assertDatabaseHas('store_settings', [
        'store_name' => 'Mava Mart',
        'printer_connection_mode' => 'imin_inner_printer',
    ]);
});

test('default metode printer toko memakai imin inner printer', function () {
    $setting = StoreSetting::query()->create(StoreSetting::defaults());

    expect($setting->printer_connection_mode)->toBe('imin_inner_printer');
});

test('payload checkout membawa pengaturan struk dan printer toko', function () {
    $user = User::factory()->create(['role' => 'owner']);

    StoreSetting::current()->update([
        'tagline' => 'Cepat, rapi, dan hemat',
        'instagram' => '@mavapos',
        'receipt_footer_note' => 'Barang yang sudah dibeli tidak dapat dikembalikan.',
        'receipt_paper_width' => '80',
        'receipt_show_logo' => false,
        'receipt_show_store_address' => false,
        'receipt_show_cashier' => true,
        'printer_auto_print' => true,
        'printer_close_after_print' => true,
        'printer_connection_mode' => 'imin_inner_printer',
        'printer_bluetooth_service_uuid' => 'service-test',
        'printer_bluetooth_characteristic_uuid' => 'char-test',
    ]);

    $this->actingAs($user)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-SKU-001', 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => 18000,
        ])
        ->assertOk()
        ->assertJsonPath('sale.store.tagline', 'Cepat, rapi, dan hemat')
        ->assertJsonPath('sale.store.instagram', '@mavapos')
        ->assertJsonPath('sale.receipt.footer_note', 'Barang yang sudah dibeli tidak dapat dikembalikan.')
        ->assertJsonPath('sale.receipt.paper_width', '80')
        ->assertJsonPath('sale.receipt.show_logo', false)
        ->assertJsonPath('sale.receipt.show_store_address', false)
        ->assertJsonPath('sale.receipt.show_cashier', true)
        ->assertJsonPath('sale.printer.auto_print', true)
        ->assertJsonPath('sale.printer.close_after_print', true)
        ->assertJsonPath('sale.printer.connection_mode', 'imin_inner_printer')
        ->assertJsonPath('sale.printer.bluetooth_service_uuid', 'service-test')
        ->assertJsonPath('sale.printer.bluetooth_characteristic_uuid', 'char-test');
});

test('template print nota memakai typography kecil dan item rata kiri kanan', function () {
    $script = file_get_contents(resource_path('js/app.js'));

    expect($script)->toContain('receiptTypography()')
        ->and($script)->toContain('font-size: ${typography.body}px')
        ->and($script)->toContain('font-size: ${typography.heading}px')
        ->and($script)->toContain('class="item-name"')
        ->and($script)->toContain('class="item-total"')
        ->and($script)->toContain('receiptLineItem')
        ->and($script)->toContain('store.tagline')
        ->and($script)->toContain('store.instagram')
        ->and($script)->toContain('Promise.all')
        ->and($script)->toContain('sendIminColumns')
        ->and($script)->toContain('colAlign')
        ->and($script)->toContain("['', 6, 0]")
        ->and($script)->not->toContain("['', 4, 0]");
});

test('layout tidak merender directive pwa sebagai teks', function () {
    $user = User::factory()->create();
    $appLayout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $fullscreenLayout = file_get_contents(resource_path('views/layouts/fullscreen-layout.blade.php'));

    expect($appLayout.$fullscreenLayout)->not->toContain('@PwaHead')
        ->and($appLayout.$fullscreenLayout)->not->toContain('@RegisterServiceWorkerScript');

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertDontSee('@PwaHead')
        ->assertDontSee('@RegisterServiceWorkerScript')
        ->assertSee('/manifest.json', false)
        ->assertSee('/sw.js', false);
});

test('owner dapat mengelola user dan role staf', function () {
    $owner = User::factory()->create([
        'role' => 'owner',
        'trial_ends_at' => now()->addDays(7),
    ]);

    $this->actingAs($owner)
        ->get('/users')
        ->assertOk()
        ->assertSee('Manajemen User')
        ->assertSee('Tambah User');

    $this->actingAs($owner)
        ->post('/users', [
            'name' => 'Kasir Cabang',
            'email' => 'kasir-cabang@example.com',
            'role' => 'kasir',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect('/users')
        ->assertSessionHas('success', 'User staf berhasil ditambahkan.');

    $staff = User::query()->where('email', 'kasir-cabang@example.com')->firstOrFail();

    expect($staff->role)->toBe('kasir')
        ->and($staff->trial_ends_at?->toDateString())->toBe($owner->trial_ends_at->toDateString());

    $this->actingAs($staff)
        ->get('/pos')
        ->assertOk()
        ->assertSee('Kasir');

    $this->actingAs($owner)
        ->patch("/users/{$staff->id}", [
            'name' => 'Admin Cabang',
            'email' => 'admin-cabang@example.com',
            'role' => 'admin',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect('/users')
        ->assertSessionHas('success', 'User staf berhasil diperbarui.');

    $this->assertDatabaseHas('users', [
        'id' => $staff->id,
        'name' => 'Admin Cabang',
        'email' => 'admin-cabang@example.com',
        'role' => 'admin',
    ]);
});

test('owner dapat menonaktifkan user staf dan tidak dapat menonaktifkan diri sendiri', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $cashier = User::factory()->create(['role' => 'kasir']);

    $this->actingAs($owner)
        ->delete("/users/{$cashier->id}")
        ->assertRedirect('/users')
        ->assertSessionHas('success', 'User staf berhasil dinonaktifkan.');

    $this->assertDatabaseMissing('users', ['id' => $cashier->id]);

    $this->actingAs($owner)
        ->delete("/users/{$owner->id}")
        ->assertRedirect('/users')
        ->assertSessionHasErrors('user');

    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

test('dashboard menampilkan status trial dan subscription expired', function () {
    $trialUser = User::factory()->create([
        'role' => 'owner',
        'trial_ends_at' => now()->addDays(3),
    ]);

    $this->actingAs($trialUser)
        ->get('/')
        ->assertOk()
        ->assertSee('Trial aktif')
        ->assertSee('3 hari tersisa');

    $expiredUser = User::factory()->create([
        'role' => 'owner',
        'trial_ends_at' => now()->subDay(),
    ]);

    $this->actingAs($expiredUser)
        ->get('/')
        ->assertOk()
        ->assertSee('Langganan berakhir')
        ->assertSee('Perpanjang sekarang');
});
