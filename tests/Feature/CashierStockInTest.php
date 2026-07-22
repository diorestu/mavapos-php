<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\User;
use App\Support\BranchInventoryManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('cashier sees focused stock in but cannot manage full inventory', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);

    $this->actingAs($cashier)->get(route('cashier-stock-in.index'))
        ->assertOk()
        ->assertSee('Stok Masuk')
        ->assertSee('cashierStockInManager', false)
        ->assertSee('Tambah Stok');

    $this->actingAs($cashier)->get(route('inventory'))->assertForbidden();
    $this->actingAs($cashier)->postJson(route('inventory.out', 'SKU-001'), ['quantity' => 1])->assertForbidden();
});

test('cashier stock in updates only the active branch and records actor', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);
    $defaultBranch = Branch::query()->firstOrFail();
    $otherBranch = Branch::query()->create(['name' => 'Cabang Lain', 'code' => 'cabang-lain', 'is_active' => true]);
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $defaultInventory = app(BranchInventoryManager::class)->forProduct($defaultBranch->id, $product);
    $otherInventory = app(BranchInventoryManager::class)->forProduct($otherBranch->id, $product);
    $before = (int) $defaultInventory->stock;

    $this->actingAs($cashier)->postJson(route('cashier-stock-in.store'), [
        'sku' => $product->sku,
        'quantity' => 4,
        'reference' => 'NOTA-1',
        'note' => 'Barang datang.',
    ])->assertCreated()
        ->assertJsonPath('stockBefore', $before)
        ->assertJsonPath('stockAfter', $before + 4);

    expect($defaultInventory->fresh()->stock)->toBe($before + 4)
        ->and($otherInventory->fresh()->stock)->toBe((int) $otherInventory->stock);

    $this->assertDatabaseHas('stock_movements', [
        'branch_id' => $defaultBranch->id,
        'created_by_user_id' => $cashier->id,
        'product_id' => $product->id,
        'type' => 'in',
        'quantity' => 4,
        'reference' => 'NOTA-1',
    ]);
});

test('cashier can stock in an active product variant', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);
    $branch = Branch::query()->firstOrFail();
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'name' => 'Besar',
        'sku' => 'SKU-001-BESAR',
        'sell_price' => 3000,
        'stock' => 2,
        'min_stock' => 0,
        'is_active' => true,
    ]);
    $inventory = app(BranchInventoryManager::class)->forVariant($branch->id, $variant);

    $this->actingAs($cashier)->postJson(route('cashier-stock-in.store'), [
        'sku' => $variant->sku,
        'quantity' => 3,
    ])->assertCreated()->assertJsonPath('item.isVariant', true);

    expect($inventory->fresh()->stock)->toBe(5);
});

test('cashier can stock in raw material from the focused stock-in page', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);
    $this->actingAs($cashier);

    $material = RawMaterial::query()->create([
        'code' => 'BB-SUSU-KASIR',
        'name' => 'Susu UHT',
        'category' => 'Bahan minuman',
        'unit' => 'liter',
        'stock' => 4.5,
        'min_stock' => 1,
        'cost_per_unit' => 18000,
    ]);

    $this->actingAs($cashier)
        ->get(route('cashier-stock-in.index'))
        ->assertOk()
        ->assertSee('Susu UHT')
        ->assertSee('raw_material', false);

    $this->actingAs($cashier)
        ->postJson(route('cashier-stock-in.store'), [
            'stock_item' => 'raw-material-'.$material->id,
            'quantity' => 2.25,
            'reference' => 'BELANJA-SUSU',
            'note' => 'Stok susu datang.',
        ])
        ->assertCreated()
        ->assertJsonPath('item.type', 'raw_material')
        ->assertJsonPath('stockBefore', 4.5)
        ->assertJsonPath('stockAfter', 6.75);

    expect((float) $material->fresh()->stock)->toBe(6.75)
        ->and($material->fresh()->note)->toBe('Stok susu datang.');
});

test('cashier stock in validates quantity and warehouse cannot use focused route', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);
    $warehouse = User::factory()->create(['role' => 'gudang']);

    $this->actingAs($cashier)->postJson(route('cashier-stock-in.store'), [
        'sku' => 'SKU-001',
        'quantity' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors('quantity');

    $this->actingAs($warehouse)->get(route('cashier-stock-in.index'))->assertForbidden();
});
