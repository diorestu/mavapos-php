<?php

use App\Models\Branch;
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

function completedSaleWithRecipe($test): array
{
    $cashier = User::factory()->create(['name' => 'Kasir Void', 'role' => 'kasir']);
    $branch = Branch::query()->firstOrFail();
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $inventory = app(BranchInventoryManager::class)->forProduct($branch->id, $product);
    $initialProductStock = (int) $inventory->stock;
    $material = RawMaterial::query()->create([
        'code' => 'RM-VOID', 'name' => 'Bahan Void', 'category' => 'Bahan utama',
        'unit' => 'kg', 'stock' => 10, 'min_stock' => 1, 'cost_per_unit' => 10000,
    ]);
    ProductRecipeItem::query()->create([
        'product_id' => $product->id, 'raw_material_id' => $material->id,
        'item_name' => $material->name, 'quantity' => 0.25, 'unit' => 'kg',
    ]);

    $test->actingAs($cashier)->postJson(route('pos.shift.start'), ['opening_cash_amount' => 50000])->assertOk();
    $checkout = $test->actingAs($cashier)->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-SKU-001', 'quantity' => 2]],
        'payment_method' => 'cash', 'discount' => 1000, 'paid_amount' => 40000,
    ])->assertOk();

    return [
        'cashier' => $cashier, 'branch' => $branch, 'product' => $product,
        'inventory' => $inventory, 'initialProductStock' => $initialProductStock,
        'material' => $material, 'sale' => PosSale::query()->where('invoice_number', $checkout->json('sale.invoice_number'))->firstOrFail(),
    ];
}

test('checkout snapshots raw material usage and owner void restores inventory exactly once', function () {
    $data = completedSaleWithRecipe($this);
    $owner = User::factory()->create(['name' => 'Owner Void', 'role' => 'owner']);

    $this->assertDatabaseHas('pos_sale_raw_material_usages', [
        'pos_sale_id' => $data['sale']->id,
        'raw_material_id' => $data['material']->id,
        'quantity' => 0.5,
        'is_legacy_fallback' => false,
    ]);
    expect((float) $data['material']->fresh()->stock)->toBe(9.5);

    $this->actingAs($owner)->postJson(route('sales.void', $data['sale']), ['reason' => 'Duplikat input'])
        ->assertOk()
        ->assertJsonPath('sale.status', 'voided');

    $data['sale']->refresh();
    expect($data['sale']->voided_at)->not->toBeNull()
        ->and($data['sale']->voided_by_user_id)->toBe($owner->id)
        ->and($data['sale']->void_reason)->toBe('Duplikat input')
        ->and($data['inventory']->fresh()->stock)->toBe($data['initialProductStock'])
        ->and((float) $data['material']->fresh()->stock)->toBe(10.0)
        ->and($data['sale']->items()->count())->toBe(1);

    $this->assertDatabaseHas('stock_movements', [
        'branch_id' => $data['branch']->id,
        'created_by_user_id' => $owner->id,
        'reference' => $data['sale']->invoice_number,
        'type' => 'in',
        'quantity' => 2,
    ]);

    $shift = CashierShift::query()->findOrFail($data['sale']->cashier_shift_id);
    expect($shift->sales_count)->toBe(0)->and($shift->net_sales)->toBe(0)->and($shift->cash_total)->toBe(0);

    $this->actingAs($owner)->postJson(route('sales.void', $data['sale']), ['reason' => 'Klik ulang'])
        ->assertStatus(409);
    expect($data['inventory']->fresh()->stock)->toBe($data['initialProductStock']);
});

test('only owner and admin can void and reason is required', function () {
    $data = completedSaleWithRecipe($this);
    $cashier = User::factory()->create(['role' => 'kasir']);
    $warehouse = User::factory()->create(['role' => 'gudang']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($cashier)->postJson(route('sales.void', $data['sale']), ['reason' => 'Tidak boleh'])->assertForbidden();
    $this->actingAs($warehouse)->postJson(route('sales.void', $data['sale']), ['reason' => 'Tidak boleh'])->assertForbidden();
    $this->actingAs($admin)->postJson(route('sales.void', $data['sale']), [])->assertUnprocessable()->assertJsonValidationErrors('reason');
});

test('sale in another active branch is not exposed to void', function () {
    $data = completedSaleWithRecipe($this);
    $owner = User::factory()->create(['role' => 'owner']);
    $otherBranch = Branch::query()->create(['name' => 'Cabang Aman', 'code' => 'cabang-aman', 'is_active' => true]);

    $this->actingAs($owner)->post(route('branches.switch'), ['branch_id' => $otherBranch->id])->assertRedirect();
    $this->actingAs($owner)->postJson(route('sales.void', $data['sale']), ['reason' => 'Cabang salah'])->assertNotFound();
    expect($data['sale']->fresh()->voided_at)->toBeNull();
});

test('sales history keeps voided sale but excludes it from active summary', function () {
    $data = completedSaleWithRecipe($this);
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner)->postJson(route('sales.void', $data['sale']), ['reason' => 'Salah transaksi'])->assertOk();

    $this->actingAs($owner)->get(route('sales'))
        ->assertOk()
        ->assertSee($data['sale']->invoice_number)
        ->assertSee('Dibatalkan')
        ->assertSee('Salah transaksi')
        ->assertSee('Rp0');
});

test('void action is rendered only for owner and admin', function () {
    $data = completedSaleWithRecipe($this);
    $owner = User::factory()->create(['role' => 'owner']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($data['cashier'])->get(route('sales'))->assertOk()->assertDontSee('Batalkan Transaksi');
    $this->actingAs($owner)->get(route('sales'))->assertOk()->assertSee('Batalkan Transaksi');
    $this->actingAs($admin)->get(route('sales'))->assertOk()->assertSee('Batalkan Transaksi');
});
