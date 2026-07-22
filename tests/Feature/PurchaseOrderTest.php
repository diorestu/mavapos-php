<?php

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Expense;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner dapat membuat purchase order tanpa langsung mengubah stok', function () {
    $user = User::factory()->create();
    $branch = Branch::query()->firstOrFail();
    $supplier = Supplier::query()->create([
        'name' => 'Supplier Kopi',
        'code' => 'SUP-KOPI',
        'status' => 'aktif',
    ]);
    $product = Product::query()->create([
        'name' => 'Biji Kopi',
        'sku' => 'BIJI-KOPI',
        'sell_price' => 50000,
        'stock' => 3,
    ]);

    BranchInventory::query()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'stock' => 3,
    ]);

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 30000,
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect(route('purchase-orders.index'))
        ->assertSessionHas('success');

    $order = PurchaseOrder::query()->firstOrFail();

    expect($order->status)->toBe('draft');
    expect($order->total_amount)->toBe(300000);
    expect(BranchInventory::query()
        ->where('branch_id', $branch->id)
        ->where('product_id', $product->id)
        ->value('stock'))->toBe(3);
});

test('purchase order diterima akan menambah stok dan mencatat expense', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $branch = app(\App\Support\BranchContext::class)->active();
    $supplier = Supplier::query()->create([
        'name' => 'Supplier Kopi',
        'code' => 'SUP-KOPI',
        'status' => 'aktif',
    ]);
    $product = Product::query()->create([
        'name' => 'Biji Kopi',
        'sku' => 'BIJI-KOPI',
        'sell_price' => 50000,
        'buy_price' => 25000,
        'stock' => 3,
    ]);

    BranchInventory::query()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'stock' => 3,
    ]);

    $order = PurchaseOrder::query()->create([
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'user_id' => $user->id,
        'po_number' => 'PO-TEST-001',
        'status' => 'draft',
        'quantity' => 10,
        'unit_cost' => 30000,
        'total_amount' => 300000,
        'ordered_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('purchase-orders.receive', $order))
        ->assertRedirect(route('purchase-orders.index'))
        ->assertSessionHas('success');

    $order->refresh();

    expect($order->status)->toBe('received');
    expect($order->expense_id)->not->toBeNull();
    expect($order->stock_movement_id)->not->toBeNull();
    expect(BranchInventory::query()
        ->where('branch_id', $branch->id)
        ->where('product_id', $product->id)
        ->value('stock'))->toBe(13);
    expect(Product::query()->whereKey($product->id)->value('buy_price'))->toBe(30000);
    expect(Expense::query()->where('reference', 'PO-TEST-001')->value('amount'))->toBe(300000);
    expect(StockMovement::query()->where('reference', 'PO-TEST-001')->value('quantity'))->toBe(10);
});

test('purchase order draft dapat dibatalkan tanpa mengubah stok', function () {
    $user = User::factory()->create();
    $branch = Branch::query()->firstOrFail();
    $supplier = Supplier::query()->create([
        'name' => 'Supplier Kopi',
        'code' => 'SUP-KOPI',
        'status' => 'aktif',
    ]);
    $product = Product::query()->create([
        'name' => 'Biji Kopi',
        'sku' => 'BIJI-KOPI',
        'sell_price' => 50000,
        'stock' => 3,
    ]);

    BranchInventory::query()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'stock' => 3,
    ]);

    $order = PurchaseOrder::query()->create([
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'user_id' => $user->id,
        'po_number' => 'PO-TEST-002',
        'status' => 'draft',
        'quantity' => 10,
        'unit_cost' => 30000,
        'total_amount' => 300000,
        'ordered_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('purchase-orders.cancel', $order))
        ->assertRedirect(route('purchase-orders.index'))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe('cancelled');
    expect(BranchInventory::query()
        ->where('branch_id', $branch->id)
        ->where('product_id', $product->id)
        ->value('stock'))->toBe(3);
});
