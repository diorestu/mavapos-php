<?php

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\SaleRefund;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AdminSaleEditorService;
use App\Services\SaleBranchTransferService;
use App\Services\SalesRefundService;
use App\Services\TransactionVoidService;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function refundableSale($test, array $overrides = []): array
{
    $actor = User::factory()->create(['role' => 'owner']);
    $test->actingAs($actor);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create(['user_id' => $actor->id, 'sku' => 'REFUND', 'name' => 'Kopi', 'buy_price' => 1, 'sell_price' => 100, 'stock' => 7, 'min_stock' => 0, 'stock_mode' => 'inventory']);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 7]);
    $shift = CashierShift::query()->create(['user_id' => $actor->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $sale = PosSale::query()->create(array_merge(['cashier_shift_id' => $shift->id, 'branch_id' => $branch->id, 'user_id' => $actor->id, 'invoice_number' => 'INV-REFUND', 'payment_method' => 'cash', 'subtotal' => 300, 'discount' => 200, 'total' => 100, 'paid_amount' => 100, 'change_amount' => 0, 'sold_at' => now()], $overrides));
    $item = $sale->items()->create(['product_id' => $product->id, 'item_type' => 'product', 'name' => 'Kopi', 'sku' => 'REFUND', 'quantity' => 3, 'unit_price' => 100, 'line_total' => 300]);

    return [$actor, $branch, $product, $sale, $item];
}

function refundInput($item, array $overrides = []): array
{
    return array_merge(['client_refund_id' => (string) Str::uuid(), 'refund_method' => 'cash', 'reason' => 'Produk dikembalikan', 'money_returned' => true, 'items' => [['pos_sale_item_id' => $item->id, 'quantity' => 1, 'restock' => false]]], $overrides);
}

test('partial refunds allocate discounted rupiah cumulatively and replay without double stock', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    $service = app(SalesRefundService::class);
    $input = refundInput($item, ['items' => [['pos_sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true]]]);
    $first = $service->refund($sale, $branch->id, $actor, $input);
    expect($first->amount)->toBe(33)
        ->and($service->refund($sale, $branch->id, $actor, $input)->id)->toBe($first->id)
        ->and(app(BranchInventoryManager::class)->forProduct($branch->id, $product)->fresh()->stock)->toBe(8)
        ->and(StockMovement::query()->count())->toBe(1);
    $second = $service->refund($sale, $branch->id, $actor, refundInput($item));
    $third = $service->refund($sale, $branch->id, $actor, refundInput($item));
    expect([$second->amount, $third->amount])->toBe([33, 34])
        ->and(SaleRefund::query()->sum('amount'))->toBe(100)
        ->and($sale->fresh()->total)->toBe(100)
        ->and($item->fresh()->quantity)->toBe(3);
    expect(fn () => $service->refund($sale, $branch->id, $actor, refundInput($item)))->toThrow(ValidationException::class);
});

test('merchant refunds never exceed net receipt and reject changed idempotent payload', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this, ['payment_method' => 'online_merchant', 'paid_amount' => 50]);
    $input = refundInput($item, ['items' => [['pos_sale_item_id' => $item->id, 'quantity' => 3, 'restock' => false]]]);
    $service = app(SalesRefundService::class);
    expect($service->refund($sale, $branch->id, $actor, $input)->amount)->toBe(50);
    expect(fn () => $service->refund($sale, $branch->id, $actor, [...$input, 'reason' => 'Changed']))->toThrow(HttpException::class);
});

test('cashiers and other branches cannot refund and duplicate item lines are rejected', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    $service = app(SalesRefundService::class);
    $cashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $actor->id]);
    expect(fn () => $service->refund($sale, $branch->id, $cashier, refundInput($item)))->toThrow(HttpException::class);
    expect(fn () => $service->refund($sale, $branch->id + 1, $actor, refundInput($item)))->toThrow(ModelNotFoundException::class);
    $input = refundInput($item);
    $input['items'][] = $input['items'][0];
    expect(fn () => $service->refund($sale, $branch->id, $actor, $input))->toThrow(ValidationException::class);
    expect(SaleRefund::query()->count())->toBe(0);
});

test('refunded sales cannot be voided edited or transferred', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    app(SalesRefundService::class)->refund($sale, $branch->id, $actor, refundInput($item));
    expect(fn () => app(TransactionVoidService::class)->void($sale, $branch->id, $actor, 'Void'))
        ->toThrow(HttpException::class);
    expect(fn () => app(AdminSaleEditorService::class)->update($sale, $branch->id, $actor, []))
        ->toThrow(HttpException::class);
    expect(fn () => app(SaleBranchTransferService::class)->transfer($sale, $branch->id, 999, $actor, 'Move'))
        ->toThrow(HttpException::class);
});

test('free returns carry zero refund and no restock by default', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this, ['payment_method' => 'free', 'total' => 0, 'discount' => 300, 'paid_amount' => 0]);
    $refund = app(SalesRefundService::class)->refund($sale, $branch->id, $actor, refundInput($item));
    expect($refund->amount)->toBe(0)->and(StockMovement::query()->count())->toBe(0);
});

test('multiple original lines allocate all merchant net rupiah regardless return order', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this, ['payment_method' => 'online_merchant', 'total' => 100, 'paid_amount' => 37, 'subtotal' => 400]);
    $second = $sale->items()->create(['product_id' => $product->id, 'item_type' => 'product', 'name' => 'Extra', 'sku' => 'EXTRA', 'quantity' => 2, 'unit_price' => 50, 'line_total' => 100]);
    $service = app(SalesRefundService::class);
    $firstRefund = $service->refund($sale, $branch->id, $actor, refundInput($second, ['items' => [['pos_sale_item_id' => $second->id, 'quantity' => 2, 'restock' => false]]]));
    $secondRefund = $service->refund($sale, $branch->id, $actor, refundInput($item, ['items' => [['pos_sale_item_id' => $item->id, 'quantity' => 3, 'restock' => false]]]));
    expect($firstRefund->amount)->toBe(10)->and($secondRefund->amount)->toBe(27)->and(SaleRefund::query()->sum('amount'))->toBe(37);
});

test('recipe returns never recreate raw material stock and require manual money confirmation', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    $product->update(['stock_mode' => 'recipe']);
    $material = RawMaterial::query()->create(['user_id' => $actor->id, 'code' => 'REFUND-RM', 'name' => 'Susu', 'unit' => 'ml', 'stock' => 100, 'min_stock' => 0, 'cost_per_unit' => 1]);
    $sale->rawMaterialUsages()->create(['raw_material_id' => $material->id, 'quantity' => 20, 'unit' => 'ml', 'is_legacy_fallback' => false]);
    $service = app(SalesRefundService::class);
    expect(fn () => $service->refund($sale, $branch->id, $actor, refundInput($item, ['items' => [['pos_sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true]]])))
        ->toThrow(ValidationException::class);
    expect(fn () => $service->refund($sale, $branch->id, $actor, refundInput($item, ['money_returned' => false])))
        ->toThrow(ValidationException::class);
    $service->refund($sale, $branch->id, $actor, refundInput($item));
    expect(StockMovement::query()->count())->toBe(0)->and((float) $material->fresh()->stock)->toBe(100.0);
});

test('HTTP refund flow renders manual confirmation saves selected rows and shows history', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    $other = $sale->items()->create(['product_id' => $product->id, 'item_type' => 'product', 'name' => 'Unselected', 'sku' => 'UNSELECTED', 'quantity' => 1, 'unit_price' => 0, 'line_total' => 0]);
    $this->get(route('sales.refund', $sale))->assertOk()->assertSee('Saya telah mengembalikan uang')->assertSee('payment gateway');
    $input = refundInput($item);
    $input['items'][] = ['pos_sale_item_id' => $other->id, 'quantity' => '0', 'restock' => '0'];
    $this->post(route('sales.refund.store', $sale), $input)->assertRedirect(route('sales.refund', $sale));
    $this->get(route('sales.refund', $sale))->assertOk()->assertSee(SaleRefund::query()->sole()->refund_number)->assertSee('Produk dikembalikan');
    $this->postJson(route('sales.refund.store', $sale), refundInput($item))->assertOk()->assertJsonPath('refund.amount', 33);
    $cashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $actor->id]);
    $this->actingAs($cashier)->get(route('sales.refund', $sale))->assertForbidden();
    $this->postJson(route('sales.refund.store', $sale), refundInput($item))->assertForbidden();
});

test('refunds reject void sales and another tenants sale', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    $sale->update(['voided_at' => now()]);
    $this->postJson(route('sales.refund.store', $sale), refundInput($item))->assertUnprocessable();
    $sale->update(['voided_at' => null]);
    $otherOwner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($otherOwner)->withSession(['active_branch_id' => null]);
    $this->postJson(route('sales.refund.store', $sale), refundInput($item))->assertNotFound();
    expect(SaleRefund::query()->count())->toBe(0);
});

test('variant returns restore only selected branch variant inventory and record the actor', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    $variant = $product->variants()->create(['name' => 'Large', 'sku' => 'REFUND-LARGE', 'stock' => 4, 'min_stock' => 0, 'sell_price' => 0, 'buy_price' => 0]);
    app(BranchInventoryManager::class)->forVariant($branch->id, $variant)->update(['stock' => 4]);
    $item->update(['product_variant_id' => $variant->id]);
    $refund = app(SalesRefundService::class)->refund($sale, $branch->id, $actor, refundInput($item, ['items' => [['pos_sale_item_id' => $item->id, 'quantity' => 2, 'restock' => true]]]));
    $movement = StockMovement::query()->sole();
    expect(app(BranchInventoryManager::class)->forVariant($branch->id, $variant)->fresh()->stock)->toBe(6)
        ->and(app(BranchInventoryManager::class)->forProduct($branch->id, $product)->fresh()->stock)->toBe(7)
        ->and($movement->created_by_user_id)->toBe($actor->id)
        ->and($movement->product_variant_id)->toBe($variant->id)
        ->and($movement->reference)->toBe($refund->refund_number)
        ->and($refund->cashier_shift_id)->toBe($sale->cashier_shift_id);
});

test('owner refunds without an open personal shift do not change attribution to original cashier shift', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    $sale->shift->update(['closed_at' => now()]);
    $refund = app(SalesRefundService::class)->refund($sale, $branch->id, $actor, refundInput($item));
    expect($refund->cashier_shift_id)->toBeNull()->and($refund->branch_id)->toBe($branch->id);
});

test('closed original sale shifts with refunded sales cannot be deleted', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    $shift = $sale->shift;
    $shift->update(['closed_at' => now()]);
    $refund = app(SalesRefundService::class)->refund($sale, $branch->id, $actor, refundInput($item));
    expect($refund->cashier_shift_id)->toBeNull();

    $this->deleteJson(route('cashier-shifts.destroy', $shift))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Shift dengan riwayat refund tidak dapat dihapus.');

    expect($shift->fresh())->not->toBeNull()
        ->and($sale->fresh())->not->toBeNull()
        ->and($item->fresh())->not->toBeNull()
        ->and($refund->fresh())->not->toBeNull();
});

test('closed processing shifts with refunds on other shifts cannot be deleted', function () {
    [$actor, $branch, $product, $sale, $item] = refundableSale($this);
    $sale->shift->update(['closed_at' => now()]);
    $processingShift = CashierShift::query()->create(['user_id' => $actor->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $refund = app(SalesRefundService::class)->refund($sale, $branch->id, $actor, refundInput($item));
    expect($refund->cashier_shift_id)->toBe($processingShift->id);
    $processingShift->update(['closed_at' => now()]);

    $this->deleteJson(route('cashier-shifts.destroy', $processingShift))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Shift dengan riwayat refund tidak dapat dihapus.');

    expect($processingShift->fresh())->not->toBeNull()
        ->and($refund->fresh()->cashier_shift_id)->toBe($processingShift->id);
});
