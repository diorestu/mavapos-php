<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
use App\Services\SaleBranchTransferService;
use App\Support\BranchInventoryManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('memindahkan transaksi memindahkan cabang, shift, dan stok', function () {
    $owner = User::query()->where('email', 'test@example.com')->firstOrFail();
    $source = Branch::query()->create(['user_id' => $owner->id, 'name' => 'Cabang A', 'code' => 'cabang-a', 'is_active' => true]);
    $target = Branch::query()->create(['user_id' => $owner->id, 'name' => 'Cabang B', 'code' => 'cabang-b', 'is_active' => true]);
    $sourceCashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $source->id]);
    $targetCashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $target->id]);
    $sourceShift = CashierShift::query()->create(['user_id' => $sourceCashier->id, 'branch_id' => $source->id, 'opened_at' => now()]);
    $targetShift = CashierShift::query()->create(['user_id' => $targetCashier->id, 'branch_id' => $target->id, 'opened_at' => now()]);
    $product = Product::withoutGlobalScopes()->where('sku', 'SKU-001')->firstOrFail();
    $inventory = app(BranchInventoryManager::class);
    $inventory->forProduct($source->id, $product)->update(['stock' => 2]);
    $inventory->forProduct($target->id, $product)->update(['stock' => 5]);
    $sale = PosSale::query()->create(['cashier_shift_id' => $sourceShift->id, 'branch_id' => $source->id, 'user_id' => $sourceCashier->id, 'invoice_number' => 'POS-TRANSFER-001', 'payment_method' => 'cash', 'subtotal' => 18000, 'discount' => 0, 'total' => 18000, 'paid_amount' => 18000, 'change_amount' => 0, 'sold_at' => now()]);
    $sale->items()->create(['product_id' => $product->id, 'item_type' => 'product', 'name' => $product->name, 'sku' => $product->sku, 'quantity' => 1, 'unit_price' => 18000, 'line_total' => 18000]);

    $this->actingAs($owner);
    $moved = app(SaleBranchTransferService::class)->transfer($sale, $source->id, $targetShift->id, $owner, 'Salah cabang saat checkout');

    expect($moved->branch_id)->toBe($target->id)->and($moved->cashier_shift_id)->toBe($targetShift->id);
    expect($inventory->forProduct($source->id, $product)->fresh()->stock)->toBe(3)
        ->and($inventory->forProduct($target->id, $product)->fresh()->stock)->toBe(4);
});
