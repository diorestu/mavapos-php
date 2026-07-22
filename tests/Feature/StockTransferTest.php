<?php

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner dapat transfer stok antar cabang dan mutasi tercatat', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $fromBranch = app(\App\Support\BranchContext::class)->active();
    $fromBranch->update(['user_id' => $user->id, 'is_active' => true]);
    $toBranch = Branch::query()->create([
        'name' => 'Cabang Kedua',
        'code' => 'cabang-kedua',
        'is_active' => true,
        'user_id' => $user->id,
    ]);
    $product = Product::query()->create([
        'name' => 'Kopi Susu',
        'sku' => 'KOPI-SUSU',
        'sell_price' => 18000,
        'stock' => 10,
        'min_stock' => 2,
    ]);

    BranchInventory::query()->create([
        'branch_id' => $fromBranch->id,
        'product_id' => $product->id,
        'stock' => 10,
        'min_stock' => 2,
    ]);
    BranchInventory::query()->create([
        'branch_id' => $toBranch->id,
        'product_id' => $product->id,
        'stock' => 1,
        'min_stock' => 2,
    ]);

    $this->actingAs($user)
        ->post(route('stock-transfers.store'), [
            'from_branch_id' => $fromBranch->id,
            'to_branch_id' => $toBranch->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'note' => 'Restok cabang kedua',
        ])
        ->assertRedirect(route('stock-transfers.index'))
        ->assertSessionHas('success');

    expect(BranchInventory::query()
        ->where('branch_id', $fromBranch->id)
        ->where('product_id', $product->id)
        ->value('stock'))->toBe(6);
    expect(BranchInventory::query()
        ->where('branch_id', $toBranch->id)
        ->where('product_id', $product->id)
        ->value('stock'))->toBe(5);
    expect(StockTransfer::query()->count())->toBe(1);
    expect(StockMovement::query()->where('reference', StockTransfer::query()->first()->transfer_number)->count())->toBe(2);
});

test('transfer stok ditolak jika stok cabang asal tidak cukup', function () {
    $user = User::factory()->create();
    $fromBranch = Branch::query()->firstOrFail();
    $toBranch = Branch::query()->create([
        'name' => 'Cabang Kedua',
        'code' => 'cabang-kedua',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'name' => 'Roti',
        'sku' => 'ROTI',
        'sell_price' => 12000,
        'stock' => 2,
    ]);

    BranchInventory::query()->create([
        'branch_id' => $fromBranch->id,
        'product_id' => $product->id,
        'stock' => 2,
    ]);

    $this->actingAs($user)
        ->from(route('stock-transfers.index'))
        ->post(route('stock-transfers.store'), [
            'from_branch_id' => $fromBranch->id,
            'to_branch_id' => $toBranch->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ])
        ->assertRedirect(route('stock-transfers.index'))
        ->assertSessionHasErrors('quantity');

    expect(StockTransfer::query()->count())->toBe(0);
});

test('owner dapat memilih dan transfer stok bahan baku antar cabang', function () {
    $user = User::factory()->create();
    $fromBranch = Branch::query()->firstOrFail();
    $toBranch = Branch::query()->create([
        'name' => 'Cabang Bahan Baku',
        'code' => 'cabang-bahan-baku',
        'is_active' => true,
    ]);
    $material = RawMaterial::query()->create([
        'code' => 'BB-GULA-TRANSFER',
        'name' => 'Gula aren transfer',
        'category' => 'Bahan minuman',
        'unit' => 'ml',
        'stock' => 1000,
        'min_stock' => 100,
        'cost_per_unit' => 75,
    ]);

    DB::table('branch_raw_material_inventories')->insert([
        [
            'branch_id' => $fromBranch->id,
            'raw_material_id' => $material->id,
            'stock' => 1000,
            'min_stock' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'branch_id' => $toBranch->id,
            'raw_material_id' => $material->id,
            'stock' => 25,
            'min_stock' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($user)
        ->get(route('stock-transfers.index'))
        ->assertOk()
        ->assertSee('Gula aren transfer')
        ->assertSee('Bahan baku');

    $this->actingAs($user)
        ->post(route('stock-transfers.store'), [
            'from_branch_id' => $fromBranch->id,
            'to_branch_id' => $toBranch->id,
            'stock_item' => 'raw-material-'.$material->id,
            'quantity' => 125,
            'note' => 'Transfer gula ke cabang',
        ])
        ->assertRedirect(route('stock-transfers.index'))
        ->assertSessionHas('success');

    expect((float) DB::table('branch_raw_material_inventories')
        ->where('branch_id', $fromBranch->id)
        ->where('raw_material_id', $material->id)
        ->value('stock'))->toBe(875.0);
    expect((float) DB::table('branch_raw_material_inventories')
        ->where('branch_id', $toBranch->id)
        ->where('raw_material_id', $material->id)
        ->value('stock'))->toBe(150.0);

    $transfer = StockTransfer::query()->firstOrFail();
    expect($transfer->raw_material_id)->toBe($material->id)
        ->and($transfer->product_id)->toBeNull();
});

test('route demo template tidak lagi terbuka di area aplikasi', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/form-elements')->assertNotFound();
    $this->actingAs($user)->get('/basic-tables')->assertNotFound();
    $this->actingAs($user)->get('/line-chart')->assertNotFound();
    $this->actingAs($user)->get('/alerts')->assertNotFound();
});
