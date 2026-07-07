<?php

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchInventoryManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('kasir wajib mulai shift sebelum checkout dan report menampilkan pendapatan per kasir', function () {
    $cashier = User::factory()->create([
        'name' => 'Kasir Pagi',
        'email' => 'kasir-pagi@example.com',
    ]);

    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $startingStock = $product->stock;

    $this->actingAs($cashier)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-SKU-001', 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => 20000,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Mulai shift kasir sebelum menyelesaikan transaksi.');

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'), [
            'opening_cash_amount' => 150000,
            'opening_note' => 'Mulai shift pagi.',
        ])
        ->assertOk()
        ->assertJsonPath('shift.cashier', 'Kasir Pagi')
        ->assertJsonPath('shift.openingCashAmount', 150000)
        ->assertJsonPath('shift.cashInDrawer', 150000);

    $this->actingAs($cashier)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-SKU-001', 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => 20000,
        ])
        ->assertOk()
        ->assertJsonPath('sale.total', 18000)
        ->assertJsonPath('sale.change_amount', 2000)
        ->assertJsonPath('sale.items.0.name', 'Kopi Susu Aren 250ml')
        ->assertJsonPath('sale.items.0.quantity', 1)
        ->assertJsonPath('shift.salesCount', 1)
        ->assertJsonPath('shift.netSales', 18000)
        ->assertJsonPath('shift.cashInDrawer', 168000);

    expect(Product::query()->where('sku', 'SKU-001')->value('stock'))->toBe($startingStock - 1);

    $this->assertDatabaseHas('pos_sales', [
        'user_id' => $cashier->id,
        'subtotal' => 18000,
        'total' => 18000,
        'payment_method' => 'cash',
    ]);
    $this->assertDatabaseHas('cashier_shifts', [
        'user_id' => $cashier->id,
        'opening_cash_amount' => 150000,
    ]);
    $this->assertDatabaseHas('pos_sale_items', [
        'name' => 'Kopi Susu Aren 250ml',
        'sku' => 'SKU-001',
        'quantity' => 1,
        'unit_price' => 18000,
    ]);

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.close'), [
            'closing_note' => 'Tutup shift pagi.',
        ])
        ->assertOk()
        ->assertJsonPath('shift.salesCount', 1)
        ->assertJsonPath('shift.netSales', 18000);

    expect(CashierShift::query()->where('user_id', $cashier->id)->whereNull('closed_at')->exists())->toBeFalse();
    expect(PosSale::query()->where('user_id', $cashier->id)->count())->toBe(1);

    $this->actingAs($cashier)
        ->get(route('reports'))
        ->assertOk()
        ->assertSee('Pendapatan per Kasir')
        ->assertSee('Kasir Pagi')
        ->assertSee('Rp18.000');
});

test('shift kasir aktif harus ditutup sebelum kasir lain memulai pekerjaan', function () {
    $firstCashier = User::factory()->create(['name' => 'Kasir Pertama']);
    $secondCashier = User::factory()->create(['name' => 'Kasir Kedua']);

    $this->actingAs($firstCashier)
        ->postJson(route('pos.shift.start'))
        ->assertOk()
        ->assertJsonPath('shift.cashier', 'Kasir Pertama');

    $this->actingAs($secondCashier)
        ->postJson(route('pos.shift.start'))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Kasir Kasir Pertama masih aktif. Tutup kasir tersebut sebelum memulai shift baru.');
});

test('halaman penjualan menampilkan transaksi pos dan filter invoice', function () {
    $cashier = User::factory()->create([
        'name' => 'Kasir Sales',
        'email' => 'kasir-sales@example.com',
    ]);

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $checkout = $this->actingAs($cashier)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-SKU-001', 'quantity' => 2],
            ],
            'payment_method' => 'qris',
            'discount' => 1000,
            'paid_amount' => 0,
        ])
        ->assertOk();

    $invoice = $checkout->json('sale.invoice_number');

    $this->actingAs($cashier)
        ->get(route('sales'))
        ->assertOk()
        ->assertSee('Daftar Invoice')
        ->assertSee($invoice)
        ->assertSee('Kasir Sales')
        ->assertSee('Rp35.000')
        ->assertSee('QRIS');

    $this->actingAs($cashier)
        ->get(route('sales', ['search' => $invoice]))
        ->assertOk()
        ->assertSee($invoice)
        ->assertSee('Kopi Susu Aren 250ml');
});

test('cabang aktif mengatur scope transaksi kasir dan laporan', function () {
    $cashier = User::factory()->create([
        'name' => 'Kasir Cabang Dua',
        'email' => 'kasir-cabang-dua@example.com',
    ]);
    $defaultBranch = Branch::query()->firstOrFail();
    $secondBranch = Branch::query()->create([
        'name' => 'Cabang Dua',
        'code' => 'cabang-dua',
        'is_active' => true,
    ]);
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    app(BranchInventoryManager::class)->forProduct($secondBranch->id, $product)->update(['stock' => 10]);

    $this->actingAs($cashier)
        ->post(route('branches.switch'), ['branch_id' => $secondBranch->id])
        ->assertRedirect();

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'), [
            'opening_cash_amount' => 50000,
        ])
        ->assertOk()
        ->assertJsonPath('shift.branch', 'Cabang Dua');

    $this->actingAs($cashier)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-SKU-001', 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => 20000,
        ])
        ->assertOk();

    $this->assertDatabaseHas('pos_sales', [
        'user_id' => $cashier->id,
        'branch_id' => $secondBranch->id,
        'total' => 18000,
    ]);

    $this->actingAs($cashier)
        ->get(route('reports'))
        ->assertOk()
        ->assertSee('Cabang aktif: Cabang Dua')
        ->assertSee('Kasir Cabang Dua');

    $this->actingAs($cashier)
        ->post(route('branches.switch'), ['branch_id' => $defaultBranch->id])
        ->assertRedirect();

    $this->actingAs($cashier)
        ->get(route('reports'))
        ->assertOk()
        ->assertSee('Cabang aktif: Cabang Utama')
        ->assertSee('Belum ada transaksi POS dalam periode ini.');
});

test('stok produk berbeda per cabang dan checkout hanya mengurangi cabang aktif', function () {
    $cashier = User::factory()->create([
        'name' => 'Kasir Stok Cabang',
        'email' => 'kasir-stok-cabang@example.com',
    ]);
    $defaultBranch = Branch::query()->firstOrFail();
    $secondBranch = Branch::query()->create([
        'name' => 'Cabang Stok',
        'code' => 'cabang-stok',
        'is_active' => true,
    ]);
    app(BranchInventoryManager::class)->initializeBranch($secondBranch->id);

    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $defaultInventory = app(BranchInventoryManager::class)->forProduct($defaultBranch->id, $product);
    $secondInventory = BranchInventory::query()
        ->where('branch_id', $secondBranch->id)
        ->where('product_id', $product->id)
        ->firstOrFail();

    $defaultInventory->update(['stock' => 40, 'min_stock' => 5]);
    $secondInventory->update(['stock' => 3, 'min_stock' => 1]);

    $this->actingAs($cashier)
        ->post(route('branches.switch'), ['branch_id' => $secondBranch->id])
        ->assertRedirect();

    $this->actingAs($cashier)
        ->get(route('pos'))
        ->assertOk();

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $this->actingAs($cashier)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-SKU-001', 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => 20000,
        ])
        ->assertOk();

    expect($defaultInventory->fresh()->stock)->toBe(40);
    expect($secondInventory->fresh()->stock)->toBe(2);
});

test('laporan nilai stok mengikuti stok cabang aktif', function () {
    $owner = User::factory()->create([
        'name' => 'Owner Report Cabang',
        'email' => 'owner-report-cabang@example.com',
    ]);
    $defaultBranch = Branch::query()->firstOrFail();
    $secondBranch = Branch::query()->create([
        'name' => 'Cabang Report Stok',
        'code' => 'cabang-report-stok',
        'is_active' => true,
    ]);
    app(BranchInventoryManager::class)->initializeBranch($secondBranch->id);

    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    app(BranchInventoryManager::class)->forProduct($defaultBranch->id, $product)->update(['stock' => 40]);
    app(BranchInventoryManager::class)->forProduct($secondBranch->id, $product)->update(['stock' => 3]);

    $this->actingAs($owner)
        ->post(route('branches.switch'), ['branch_id' => $defaultBranch->id])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('reports'))
        ->assertOk()
        ->assertSee('Cabang aktif: Cabang Utama')
        ->assertSee('40');

    $this->actingAs($owner)
        ->post(route('branches.switch'), ['branch_id' => $secondBranch->id])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('reports'))
        ->assertOk()
        ->assertSee('Cabang aktif: Cabang Report Stok')
        ->assertSee('3');
});

test('halaman stok mengikuti stok cabang aktif', function () {
    $owner = User::factory()->create([
        'name' => 'Owner Stok Cabang',
        'email' => 'owner-stok-cabang@example.com',
    ]);
    $defaultBranch = Branch::query()->firstOrFail();
    $secondBranch = Branch::query()->create([
        'name' => 'Cabang Kelola Stok',
        'code' => 'cabang-kelola-stok',
        'is_active' => true,
    ]);
    app(BranchInventoryManager::class)->initializeBranch($secondBranch->id);

    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    app(BranchInventoryManager::class)->forProduct($defaultBranch->id, $product)->update(['stock' => 44]);
    app(BranchInventoryManager::class)->forProduct($secondBranch->id, $product)->update(['stock' => 7]);

    $this->actingAs($owner)
        ->post(route('branches.switch'), ['branch_id' => $defaultBranch->id])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('inventory'))
        ->assertOk()
        ->assertSee('Cabang aktif: <span class="font-semibold text-gray-700 dark:text-gray-300">Cabang Utama</span>', false);

    $this->actingAs($owner)
        ->post(route('branches.switch'), ['branch_id' => $secondBranch->id])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('inventory'))
        ->assertOk()
        ->assertSee('Cabang aktif: <span class="font-semibold text-gray-700 dark:text-gray-300">Cabang Kelola Stok</span>', false);

    expect(app(BranchInventoryManager::class)->stockForProduct($defaultBranch->id, $product))->toBe(44);
    expect(app(BranchInventoryManager::class)->stockForProduct($secondBranch->id, $product))->toBe(7);
});
