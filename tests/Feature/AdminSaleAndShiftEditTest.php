<?php

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\ProductRecipeItem;
use App\Models\RawMaterial;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can edit sale items and the inventory, recipe usage, and shift summary follow the change', function () {
    $cashier = User::factory()->create(['role' => 'admin']);
    $admin = $cashier;
    $branch = app(BranchContext::class)->active();
    $firstProduct = Product::query()->create(['user_id' => $cashier->id, 'sku' => 'EDIT-ONE', 'name' => 'Produk Satu', 'buy_price' => 5000, 'sell_price' => 10000, 'stock' => 10, 'min_stock' => 0, 'stock_mode' => 'inventory']);
    $secondProduct = Product::query()->create(['user_id' => $cashier->id, 'sku' => 'EDIT-TWO', 'name' => 'Produk Dua', 'buy_price' => 5000, 'sell_price' => 15000, 'stock' => 0, 'min_stock' => 0, 'stock_mode' => 'recipe']);
    app(BranchInventoryManager::class)->forProduct($branch->id, $firstProduct)->update(['stock' => 10]);
    $material = RawMaterial::query()->create(['user_id' => $cashier->id, 'code' => 'EDIT-RM', 'name' => 'Bahan Edit', 'unit' => 'kg', 'stock' => 10, 'min_stock' => 0, 'cost_per_unit' => 1000]);
    ProductRecipeItem::query()->create(['product_id' => $secondProduct->id, 'raw_material_id' => $material->id, 'item_name' => $material->name, 'quantity' => 0.5, 'unit' => 'kg']);

    $this->actingAs($cashier)->postJson(route('pos.shift.start'), ['opening_cash_amount' => 100000])->assertOk();
    $checkout = $this->postJson(route('pos.checkout'), [
        'items' => [['id' => 'product-EDIT-ONE', 'quantity' => 2]],
        'payment_method' => 'cash', 'paid_amount' => 20000,
    ])->assertOk();
    $sale = PosSale::query()->where('invoice_number', $checkout->json('sale.invoice_number'))->firstOrFail();

    $this->actingAs($admin)->putJson(route('sales.update', $sale), [
        'items' => [['id' => 'product-EDIT-TWO', 'quantity' => 3]],
        'payment_method' => 'qris',
        'discount' => 5000,
        'customer_name' => 'Nina',
        'buyer_nationality' => 'foreigner',
        'reason' => 'Koreksi salah produk',
    ])->assertOk();

    $sale->refresh();
    expect($sale->subtotal)->toBe(45000)
        ->and($sale->discount)->toBe(5000)
        ->and($sale->total)->toBe(40000)
        ->and($sale->payment_method)->toBe('qris')
        ->and($sale->buyer_nationality)->toBe('foreigner')
        ->and($sale->items()->sole()->sku)->toBe('EDIT-TWO')
        ->and($sale->items()->sole()->quantity)->toBe(3)
        ->and(app(BranchInventoryManager::class)->forProduct($branch->id, $firstProduct)->fresh()->stock)->toBe(10)
        ->and((float) $material->fresh()->stock)->toBe(8.5);

    $shift = CashierShift::query()->findOrFail($sale->cashier_shift_id);
    expect($shift->sales_count)->toBe(3)
        ->and($shift->net_sales)->toBe(40000)
        ->and($shift->cash_total)->toBe(0)
        ->and($shift->qris_total)->toBe(40000);
});

test('admin can edit operational cashier shift data but cannot overwrite calculated sales totals', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cashier = User::factory()->create(['role' => 'kasir']);
    $branch = app(BranchContext::class)->active();
    $companion = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $admin->id]);
    $shift = CashierShift::query()->create([
        'user_id' => $cashier->id,
        'branch_id' => $branch->id,
        'opening_cash_amount' => 100000,
        'sales_count' => 7,
        'net_sales' => 75000,
    ]);

    $this->actingAs($admin)->put(route('cashier-shifts.update', $shift), [
        'opening_cash_amount' => 200000,
        'opened_at' => now()->subHour()->format('Y-m-d H:i'),
        'opening_note' => 'Kas awal dikoreksi admin.',
        'closing_note' => 'Catatan diperbarui.',
        'companion_staff_ids' => [$companion->id],
        'sales_count' => 999,
    ])->assertRedirect(route('cashier-shifts'));

    $shift->refresh();
    expect($shift->opening_cash_amount)->toBe(200000)
        ->and($shift->opening_note)->toBe('Kas awal dikoreksi admin.')
        ->and($shift->closing_note)->toBe('Catatan diperbarui.')
        ->and($shift->companion_staff_ids)->toBe([$companion->id])
        ->and($shift->sales_count)->toBe(0)
        ->and($shift->net_sales)->toBe(0);
});

test('editing a recipe sale does not create product inventory that was never deducted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create(['user_id' => $admin->id, 'sku' => 'EDIT-RECIPE', 'name' => 'Menu Resep', 'buy_price' => 5000, 'sell_price' => 10000, 'stock' => 0, 'min_stock' => 0, 'stock_mode' => 'recipe']);
    $material = RawMaterial::query()->create(['user_id' => $admin->id, 'code' => 'EDIT-RECIPE-RM', 'name' => 'Bahan Resep', 'unit' => 'kg', 'stock' => 5, 'min_stock' => 0, 'cost_per_unit' => 1000]);
    ProductRecipeItem::query()->create(['product_id' => $product->id, 'raw_material_id' => $material->id, 'item_name' => $material->name, 'quantity' => 1, 'unit' => 'kg']);

    $this->actingAs($admin)->postJson(route('pos.shift.start'), ['opening_cash_amount' => 100000])->assertOk();
    $checkout = $this->postJson(route('pos.checkout'), ['items' => [['id' => 'product-EDIT-RECIPE', 'quantity' => 2]], 'payment_method' => 'qris'])->assertOk();
    $sale = PosSale::query()->where('invoice_number', $checkout->json('sale.invoice_number'))->firstOrFail();
    $this->putJson(route('sales.update', $sale), ['items' => [['id' => 'product-EDIT-RECIPE', 'quantity' => 1]], 'payment_method' => 'qris', 'reason' => 'Koreksi jumlah'])->assertOk();

    expect(app(BranchInventoryManager::class)->forProduct($branch->id, $product)->fresh()->stock)->toBe(0)
        ->and((float) $material->fresh()->stock)->toBe(4.0);
});

test('cashier cannot edit a sale or a cashier shift', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);
    $branch = app(BranchContext::class)->active();
    $shift = CashierShift::query()->create(['user_id' => $cashier->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $sale = PosSale::query()->create(['user_id' => $cashier->id, 'cashier_shift_id' => $shift->id, 'branch_id' => $branch->id, 'invoice_number' => 'EDIT-FORBIDDEN', 'payment_method' => 'cash', 'subtotal' => 10000, 'discount' => 0, 'total' => 10000, 'paid_amount' => 10000, 'change_amount' => 0, 'sold_at' => now()]);

    $this->actingAs($cashier)->putJson(route('sales.update', $sale), [])->assertForbidden();
    $this->put(route('cashier-shifts.update', $shift), [])->assertForbidden();
});

test('owner cannot use admin-only edit endpoints', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $branch = app(BranchContext::class)->active();
    $shift = CashierShift::query()->create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $sale = PosSale::query()->create(['user_id' => $owner->id, 'cashier_shift_id' => $shift->id, 'branch_id' => $branch->id, 'invoice_number' => 'EDIT-OWNER-FORBIDDEN', 'payment_method' => 'cash', 'subtotal' => 10000, 'discount' => 0, 'total' => 10000, 'paid_amount' => 10000, 'change_amount' => 0, 'sold_at' => now()]);

    $this->actingAs($owner)->putJson(route('sales.update', $sale), [])->assertForbidden();
    $this->put(route('cashier-shifts.update', $shift), [])->assertForbidden();
});
