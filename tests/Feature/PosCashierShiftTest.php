<?php

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\ProductRecipeItem;
use App\Models\RawMaterial;
use App\Models\User;
use App\Support\BranchInventoryManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('halaman shift menghitung nominal dari transaksi meski ringkasan shift belum tersinkron', function () {
    $cashier = User::factory()->create(['name' => 'Kasir Ringkasan', 'role' => 'kasir']);
    $branch = Branch::query()->firstOrFail();
    $shift = CashierShift::query()->create([
        'user_id' => $cashier->id,
        'branch_id' => $branch->id,
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    PosSale::query()->create([
        'cashier_shift_id' => $shift->id,
        'branch_id' => $branch->id,
        'user_id' => $cashier->id,
        'invoice_number' => 'POS-SHIFT-NOMINAL',
        'payment_method' => 'cash',
        'subtotal' => 25000,
        'discount' => 2000,
        'total' => 23000,
        'paid_amount' => 25000,
        'change_amount' => 2000,
        'sold_at' => now(),
    ]);

    $this->actingAs($cashier)
        ->get(route('cashier-shifts'))
        ->assertOk()
        ->assertViewHas('shifts', fn ($shifts): bool => $shifts->getCollection()->contains(
            fn (CashierShift $shift): bool => $shift->sales_count === 1
                && $shift->net_sales === 23000
                && $shift->cash_total === 23000,
        ))
        ->assertSee('Kasir Ringkasan')
        ->assertSee('Rp23.000');
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
        ->assertJsonPath('message', 'Sesi Kasir Pertama masih aktif. Akhiri sesi tersebut sebelum kasir berikutnya mulai.');
});

test('kasir berikutnya wajib memvalidasi cash dan kartu dari rekap sesi sebelumnya', function () {
    $firstCashier = User::factory()->create(['name' => 'Kasir Pagi', 'role' => 'kasir']);
    $secondCashier = User::factory()->create(['name' => 'Kasir Siang', 'role' => 'kasir']);

    $this->actingAs($firstCashier)
        ->postJson(route('pos.shift.start'), [
            'opening_cash_amount' => 100000,
        ])
        ->assertOk();

    $this->actingAs($firstCashier)
        ->postJson(route('pos.checkout'), [
            'items' => [['id' => 'product-SKU-001', 'quantity' => 1]],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => 20000,
        ])
        ->assertOk();

    $this->actingAs($firstCashier)
        ->postJson(route('pos.checkout'), [
            'items' => [['id' => 'product-SKU-001', 'quantity' => 1]],
            'payment_method' => 'card',
            'discount' => 0,
            'paid_amount' => 0,
        ])
        ->assertOk();

    $recap = $this->actingAs($firstCashier)
        ->postJson(route('pos.shift.close'), [
            'closing_note' => 'Handover siang.',
        ])
        ->assertOk()
        ->json('recap');

    $this->actingAs($secondCashier)
        ->postJson(route('pos.shift.start'), [
            'validated_cash_amount' => $recap['expectedCashInDrawer'] - 1000,
            'validated_card_amount' => $recap['cardTotal'],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Validasi cash tidak sesuai rekap sebelumnya. Nominal wajib '.number_format($recap['expectedCashInDrawer'], 0, ',', '.').'.');

    $this->actingAs($secondCashier)
        ->postJson(route('pos.shift.start'), [
            'validated_cash_amount' => $recap['expectedCashInDrawer'],
            'validated_card_amount' => $recap['cardTotal'],
            'opening_note' => 'Cash dan kartu sudah cocok.',
        ])
        ->assertOk()
        ->assertJsonPath('shift.cashier', 'Kasir Siang')
        ->assertJsonPath('shift.openingCashAmount', $recap['expectedCashInDrawer'])
        ->assertJsonPath('shift.validatedCashAmount', $recap['expectedCashInDrawer'])
        ->assertJsonPath('shift.validatedCardAmount', $recap['cardTotal']);

    $previousShift = CashierShift::query()->where('user_id', $firstCashier->id)->firstOrFail();
    $nextShift = CashierShift::query()->where('user_id', $secondCashier->id)->firstOrFail();

    expect($nextShift->previous_cashier_shift_id)->toBe($previousShift->id)
        ->and($nextShift->handover_validated_at)->not->toBeNull();
});

test('owner dapat menutup paksa shift kasir aktif', function () {
    $cashier = User::factory()->create([
        'name' => 'Kasir Lupa Tutup',
        'role' => 'kasir',
    ]);
    $owner = User::factory()->create([
        'name' => 'Owner Shift',
        'role' => 'owner',
    ]);

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'), [
            'opening_cash_amount' => 75000,
        ])
        ->assertOk();

    $shift = CashierShift::query()
        ->where('user_id', $cashier->id)
        ->whereNull('closed_at')
        ->firstOrFail();

    PosSale::query()->create([
        'cashier_shift_id' => $shift->id,
        'branch_id' => $shift->branch_id,
        'user_id' => $cashier->id,
        'invoice_number' => 'POS-FORCE-CLOSE',
        'payment_method' => 'cash',
        'subtotal' => 25000,
        'discount' => 2000,
        'total' => 23000,
        'paid_amount' => 25000,
        'change_amount' => 2000,
        'sold_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('cashier-shifts.force-close', $shift), [
            'closing_note' => 'Ditutup paksa oleh owner karena kasir lupa.',
        ])
        ->assertRedirect(route('cashier-shifts'))
        ->assertSessionHas('success', 'Shift Kasir Lupa Tutup ditutup paksa.')
        ->assertSessionHas('shiftRecap', fn (array $recap): bool => $recap['cashier'] === 'Kasir Lupa Tutup'
            && $recap['expectedCashInDrawer'] === 98000);

    $shift->refresh();

    expect($shift->closed_at)->not->toBeNull()
        ->and($shift->sales_count)->toBe(1)
        ->and($shift->gross_sales)->toBe(25000)
        ->and($shift->discount_total)->toBe(2000)
        ->and($shift->net_sales)->toBe(23000)
        ->and($shift->cash_total)->toBe(23000)
        ->and($shift->closing_note)->toBe('Ditutup paksa oleh owner karena kasir lupa.');
});

test('tutup kasir normal mengembalikan rekap penjualan dan total uang', function () {
    $cashier = User::factory()->create(['name' => 'Kasir Rekap', 'role' => 'kasir']);
    $this->actingAs($cashier)->postJson(route('pos.shift.start'), ['opening_cash_amount' => 75000])->assertOk();
    $this->actingAs($cashier)->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-SKU-001', 'quantity' => 1]],
        'payment_method' => 'cash', 'discount' => 0, 'paid_amount' => 20000,
    ])->assertOk();

    $this->actingAs($cashier)->postJson(route('pos.shift.close'), ['closing_note' => 'Selesai.'])
        ->assertOk()
        ->assertJsonPath('recap.cashier', 'Kasir Rekap')
        ->assertJsonPath('recap.salesCount', 1)
        ->assertJsonPath('recap.cashTotal', 18000)
        ->assertJsonPath('recap.openingCashAmount', 75000)
        ->assertJsonPath('recap.expectedCashInDrawer', 93000)
        ->assertJsonPath('recap.closingNote', 'Selesai.')
        ->assertJsonPath('recap.printer.connection_mode', 'imin_inner_printer');
});

test('tutup kasir paksa json mengembalikan kontrak rekap yang sama', function () {
    $cashier = User::factory()->create(['name' => 'Kasir Rekap Paksa', 'role' => 'kasir']);
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($cashier)->postJson(route('pos.shift.start'), ['opening_cash_amount' => 50000])->assertOk();
    $shift = CashierShift::query()->where('user_id', $cashier->id)->firstOrFail();

    $this->actingAs($owner)->postJson(route('cashier-shifts.force-close', $shift), ['closing_note' => 'Paksa.'])
        ->assertOk()
        ->assertJsonPath('recap.cashier', 'Kasir Rekap Paksa')
        ->assertJsonPath('recap.salesCount', 0)
        ->assertJsonPath('recap.expectedCashInDrawer', 50000)
        ->assertJsonPath('recap.closingNote', 'Paksa.')
        ->assertJsonStructure(['recap' => ['store', 'receipt', 'printer', 'grossSales', 'discountTotal', 'netSales', 'cashTotal', 'qrisTotal', 'cardTotal']]);
});

test('kasir tidak dapat menutup paksa shift dari halaman admin', function () {
    $firstCashier = User::factory()->create([
        'name' => 'Kasir Aktif',
        'role' => 'kasir',
    ]);
    $secondCashier = User::factory()->create([
        'name' => 'Kasir Lain',
        'role' => 'kasir',
    ]);

    $this->actingAs($firstCashier)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $shift = CashierShift::query()
        ->where('user_id', $firstCashier->id)
        ->whereNull('closed_at')
        ->firstOrFail();

    $this->actingAs($secondCashier)
        ->post(route('cashier-shifts.force-close', $shift), [
            'closing_note' => 'Tidak boleh.',
        ])
        ->assertForbidden();

    expect($shift->fresh()->closed_at)->toBeNull();
});

test('admin dapat menghapus riwayat shift beserta transaksi di dalamnya', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($cashier)->postJson(route('pos.shift.start'))->assertOk();
    $checkout = $this->actingAs($cashier)->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-SKU-001', 'quantity' => 1]],
        'payment_method' => 'cash', 'discount' => 0, 'paid_amount' => 20000,
    ])->assertOk();
    $this->actingAs($cashier)->postJson(route('pos.shift.close'))->assertOk();

    $shift = CashierShift::query()->where('user_id', $cashier->id)->firstOrFail();
    $sale = PosSale::query()->where('invoice_number', $checkout->json('sale.invoice_number'))->firstOrFail();

    $this->actingAs($admin)
        ->delete('/cashier-shifts/'.$shift->id)
        ->assertRedirect(route('cashier-shifts'))
        ->assertSessionHas('success', 'Riwayat shift dan transaksi di dalamnya berhasil dihapus.');

    $this->assertDatabaseMissing('cashier_shifts', ['id' => $shift->id]);
    $this->assertDatabaseMissing('pos_sales', ['id' => $sale->id]);
    $this->assertDatabaseMissing('pos_sale_items', ['pos_sale_id' => $sale->id]);
});

test('owner dapat menghapus riwayat shift tetapi kasir tidak', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);
    $owner = User::factory()->create(['role' => 'owner']);
    $shift = CashierShift::query()->create([
        'user_id' => $cashier->id,
        'branch_id' => Branch::query()->firstOrFail()->id,
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->delete('/cashier-shifts/'.$shift->id)
        ->assertRedirect(route('cashier-shifts'));

    $this->assertDatabaseMissing('cashier_shifts', ['id' => $shift->id]);

    $cashierShift = CashierShift::query()->create([
        'user_id' => $cashier->id,
        'branch_id' => Branch::query()->firstOrFail()->id,
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    $this->actingAs($cashier)
        ->delete('/cashier-shifts/'.$cashierShift->id)
        ->assertForbidden();

    $this->assertDatabaseHas('cashier_shifts', ['id' => $cashierShift->id]);
});

test('aksi hapus riwayat shift hanya tampil untuk owner dan admin', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'owner']);
    CashierShift::query()->create([
        'user_id' => $cashier->id,
        'branch_id' => Branch::query()->firstOrFail()->id,
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('cashier-shifts'))
        ->assertOk()
        ->assertSee('Hapus Riwayat Shift');

    $this->actingAs($owner)
        ->get(route('cashier-shifts'))
        ->assertOk()
        ->assertSee('Hapus Riwayat Shift');

    $this->actingAs($cashier)
        ->get(route('cashier-shifts'))
        ->assertOk()
        ->assertDontSee('Hapus Riwayat Shift');
});

test('pilihan tutup paksa hanya tampil untuk owner dan admin', function () {
    $cashier = User::factory()->create([
        'name' => 'Kasir UI',
        'role' => 'kasir',
    ]);
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('cashier-shifts'))
        ->assertOk()
        ->assertSee('Tutup Paksa');

    $this->actingAs($cashier)
        ->get(route('cashier-shifts'))
        ->assertOk()
        ->assertDontSee('Tutup Paksa');
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

test('checkout mengurangi stok bahan baku sesuai resep produk', function () {
    $cashier = User::factory()->create([
        'name' => 'Kasir Bahan Baku',
        'email' => 'kasir-bahan-baku@example.com',
    ]);
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $material = RawMaterial::query()->create([
        'code' => 'BB-KOPI-CHECKOUT',
        'name' => 'Kopi bubuk checkout',
        'category' => 'Bahan minuman',
        'unit' => 'gram',
        'stock' => 250,
        'min_stock' => 50,
        'cost_per_unit' => 120,
    ]);
    ProductRecipeItem::query()->create([
        'product_id' => $product->id,
        'raw_material_id' => $material->id,
        'item_name' => $material->name,
        'quantity' => 18.5,
        'unit' => $material->unit,
    ]);

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $this->actingAs($cashier)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-SKU-001', 'quantity' => 2],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => 40000,
        ])
        ->assertOk();

    expect((float) $material->fresh()->stock)->toBe(213.0);
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
