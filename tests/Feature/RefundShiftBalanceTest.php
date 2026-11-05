<?php

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
use App\Services\CashierShiftSummaryService;
use App\Services\SalesRefundService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('cash refunds reduce the processing shift drawer while preserving original sales', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $this->actingAs($actor);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create(['sku' => 'REFUND-BALANCE', 'name' => 'Kopi', 'sell_price' => 10000, 'buy_price' => 1000, 'stock_mode' => 'inventory']);
    $shift = CashierShift::query()->create(['user_id' => $actor->id, 'branch_id' => $branch->id, 'opened_at' => now(), 'opening_cash_amount' => 50000]);
    $sale = PosSale::query()->create(['cashier_shift_id' => $shift->id, 'branch_id' => $branch->id, 'user_id' => $actor->id, 'invoice_number' => 'REFUND-BALANCE', 'payment_method' => 'cash', 'subtotal' => 20000, 'discount' => 0, 'total' => 20000, 'paid_amount' => 20000, 'sold_at' => now()]);
    $item = $sale->items()->create(['product_id' => $product->id, 'item_type' => 'product', 'name' => 'Kopi', 'sku' => $product->sku, 'quantity' => 2, 'unit_price' => 10000, 'line_total' => 20000]);
    app(SalesRefundService::class)->refund($sale, $branch->id, $actor, [
        'client_refund_id' => (string) Str::uuid(), 'money_returned' => true, 'refund_method' => 'cash', 'reason' => 'Satu produk dikembalikan',
        'items' => [['pos_sale_item_id' => $item->id, 'quantity' => 1, 'restock' => false]],
    ]);
    $recap = app(CashierShiftSummaryService::class)->recap($shift->fresh());
    expect($recap['cashTotal'])->toBe(20000);
    expect($recap['cashRefundTotal'])->toBe(10000);
    expect($recap['refundTotal'])->toBe(10000);
    expect($recap['expectedCashInDrawer'])->toBe(60000);
    $this->getJson(route('pos'))->assertOk()->assertJsonPath('activeShift.cashInDrawer', 60000);
});
