<?php

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
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
            'opening_note' => 'Mulai shift pagi.',
        ])
        ->assertOk()
        ->assertJsonPath('shift.cashier', 'Kasir Pagi');

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
        ->assertJsonPath('shift.netSales', 18000);

    expect(Product::query()->where('sku', 'SKU-001')->value('stock'))->toBe($startingStock - 1);

    $this->assertDatabaseHas('pos_sales', [
        'user_id' => $cashier->id,
        'subtotal' => 18000,
        'total' => 18000,
        'payment_method' => 'cash',
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
