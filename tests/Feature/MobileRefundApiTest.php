<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\SaleRefund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function mobileRefundFixture(): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $branch = Branch::query()->create(['user_id' => $owner->id, 'name' => 'Mobile Refund', 'code' => 'MOBILE-REFUND', 'is_active' => true]);
    $product = Product::query()->create(['user_id' => $owner->id, 'sku' => 'MOBILE-REFUND', 'name' => 'Kopi', 'sell_price' => 20000, 'stock_mode' => 'inventory']);
    $shift = CashierShift::query()->create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $sale = PosSale::query()->create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'cashier_shift_id' => $shift->id, 'invoice_number' => 'MOBILE-REFUND', 'payment_method' => 'cash', 'subtotal' => 60000, 'total' => 60000, 'paid_amount' => 60000, 'sold_at' => now()]);
    $item = $sale->items()->create(['product_id' => $product->id, 'item_type' => 'product', 'name' => 'Kopi', 'sku' => 'MOBILE-REFUND', 'quantity' => 3, 'unit_price' => 20000, 'line_total' => 60000]);

    return [$owner, $branch, $sale, $item];
}

test('mobile owner can inspect and record a partial refund entirely as JSON', function () {
    [$owner, $branch, $sale, $item] = mobileRefundFixture();
    $token = $owner->createToken('mobile-pos', ['mobile:pos'])->plainTextToken;
    $url = '/api/mobile/v1/sales/'.$sale->id.'/refund';
    $this->withHeader('Authorization', 'Bearer '.$token)->get($url.'?branch_id='.$branch->id)
        ->assertOk()->assertHeader('content-type', 'application/json')->assertJsonPath('items.0.pos_sale_item_id', $item->id)
        ->assertJsonPath('items.0.remaining_quantity', 3)->assertJsonPath('items.0.remaining_refund_amount', 60000);
    $body = ['branch_id' => $branch->id, 'client_refund_id' => (string) Str::uuid(), 'refund_method' => 'cash', 'reason' => 'Satu barang diretur', 'money_returned' => true,
        'items' => [['pos_sale_item_id' => $item->id, 'quantity' => 1, 'restock' => false]]];
    $this->post($url, $body)->assertOk()->assertJsonPath('refund.amount', 20000);
    $this->post($url, $body)->assertOk()->assertJsonPath('refund.amount', 20000);
    expect(SaleRefund::query()->count())->toBe(1);
    $this->get($url.'?branch_id='.$branch->id)->assertOk()->assertJsonPath('refunded_amount', 20000)
        ->assertJsonPath('items.0.remaining_quantity', 2)->assertJsonPath('items.0.remaining_refund_amount', 40000)
        ->assertJsonPath('items.0.allocated_refund_amount', 60000);
});

test('mobile cashier cannot invoke refunds and errors remain JSON', function () {
    [$owner, $branch, $sale, $item] = mobileRefundFixture();
    $cashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $branch->id]);
    $token = $cashier->createToken('mobile-pos')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/mobile/v1/sales/'.$sale->id.'/refund')
        ->assertForbidden()->assertHeader('content-type', 'application/json');
});

test('mobile refund endpoints reject cookie only authentication without modifying sales', function () {
    [$owner, $branch, $sale, $item] = mobileRefundFixture();
    $url = '/api/mobile/v1/sales/'.$sale->id.'/refund';
    $this->actingAs($owner)->get($url.'?branch_id='.$branch->id)->assertUnauthorized();
    $this->post($url, ['branch_id' => $branch->id, 'client_refund_id' => (string) Str::uuid(), 'refund_method' => 'cash', 'reason' => 'Cookie only attempt', 'money_returned' => true,
        'items' => [['pos_sale_item_id' => $item->id, 'quantity' => 1, 'restock' => false]]])->assertUnauthorized();
    expect(SaleRefund::query()->count())->toBe(0);
});
