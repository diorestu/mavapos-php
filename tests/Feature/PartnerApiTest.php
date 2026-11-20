<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PartnerApiKey;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function partnerFixture(): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $branch = Branch::create(['user_id' => $owner->id, 'name' => 'Outlet A', 'code' => 'PARTNER-A', 'is_active' => true]);
    $category = ProductCategory::create(['user_id' => $owner->id, 'name' => 'Minuman', 'code' => 'PARTNER-CAT', 'status' => 'active']);
    $product = Product::create(['user_id' => $owner->id, 'product_category_id' => $category->id, 'sku' => 'API-KOPI', 'name' => 'Kopi', 'sell_price' => 20000]);
    $shift = CashierShift::create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $sale = PosSale::create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'cashier_shift_id' => $shift->id, 'invoice_number' => 'API-001', 'payment_method' => 'split', 'subtotal' => 40000, 'discount' => 4000, 'total' => 36000, 'paid_amount' => 36000, 'sold_at' => Carbon::parse('2026-11-13T00:00:00+08:00')->utc()]);
    $sale->items()->create(['product_id' => $product->id, 'item_type' => 'product', 'name' => 'Kopi historis', 'sku' => 'API-KOPI', 'quantity' => 2, 'unit_price' => 20000, 'line_total' => 40000]);
    $sale->payments()->createMany([['payment_method' => 'cash', 'amount' => 16000], ['payment_method' => 'qris', 'amount' => 20000]]);
    [$key, $plain, $client] = PartnerApiKey::issue($owner, 'ERP', false, now()->addDays(30));

    return compact('owner', 'branch', 'product', 'sale', 'key', 'plain', 'client');
}

function partnerWindow(int $branchId): string
{
    return '?'.http_build_query(['outlet_id' => $branchId, 'from' => '2026-11-13T00:00:00+08:00', 'to' => '2026-11-14T00:00:00+08:00']);
}

test('partner endpoints require dedicated bearer credentials and reject web or mobile authentication', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->get('/api/partner/v1/outlets')->assertUnauthorized()->assertHeader('content-type', 'application/json');
    $this->actingAs($owner)->getJson('/api/partner/v1/outlets')->assertUnauthorized();
    $mobile = $owner->createToken('mobile', ['mobile:pos'])->plainTextToken;
    $this->withToken($mobile)->getJson('/api/partner/v1/outlets')->assertUnauthorized();
});

test('partner lists only owned outlets and products without cost prices', function () {
    $f = partnerFixture();
    $other = User::factory()->create(['role' => 'owner']);
    Branch::create(['user_id' => $other->id, 'name' => 'Secret', 'code' => 'SECRET']);
    Product::create(['user_id' => $other->id, 'sku' => 'SECRET', 'name' => 'Secret', 'sell_price' => 10000]);
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/v1/outlets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $f['branch']->id)->assertJsonPath('meta.timezone', 'Asia/Makassar');
    $this->getJson('/api/partner/v1/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.category.name', 'Minuman')->assertJsonMissingPath('data.0.buy_price');
});

test('partner transactions preserve captured line values split payments and timezone boundaries', function () {
    $f = partnerFixture();
    $f['product']->update(['name' => 'Renamed', 'sell_price' => 99000]);
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/v1/transactions'.partnerWindow($f['branch']->id))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.transaction_number', 'API-001')
        ->assertJsonPath('data.0.sold_at', '2026-11-13T00:00:00+08:00')->assertJsonPath('data.0.total', 36000)
        ->assertJsonPath('data.0.items.0.name', 'Kopi historis')->assertJsonPath('data.0.items.0.unit_price', 20000)
        ->assertJsonPath('data.0.items.0.discount', null)->assertJsonPath('data.0.items.0.modifiers', null)
        ->assertJsonPath('data.0.payments.1.method', 'qris')->assertJsonPath('data.0.status', 'completed');
    $this->getJson('/api/partner/v1/transactions?'.http_build_query(['outlet_id' => $f['branch']->id, 'from' => '2026-11-12T00:00:00+08:00', 'to' => '2026-11-13T00:00:00+08:00']))->assertOk()->assertJsonCount(0, 'data');
});

test('partner includes cancelled and refund states and exposes refund changes', function () {
    $f = partnerFixture();
    $f['sale']->refunds()->create(['tenant_id' => $f['owner']->id, 'branch_id' => $f['branch']->id, 'user_id' => $f['owner']->id, 'refund_number' => 'REF-API', 'client_refund_id' => 'api-refund', 'request_hash' => 'hash', 'amount' => 10000, 'refund_method' => 'cash', 'reason' => 'Return', 'refunded_at' => now()]);
    $path = '/api/partner/v1/transactions/'.$f['sale']->id;
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson($path)->assertOk()->assertJsonPath('data.status', 'partially_refunded')->assertJsonPath('data.refunded_amount', 10000);
    $this->getJson('/api/partner/v1/sales-summary'.partnerWindow($f['branch']->id))->assertOk()->assertJsonPath('data.total_sales', 36000)->assertJsonPath('data.refunded_amount', 10000)->assertJsonPath('data.net_sales', 26000)->assertJsonPath('data.products.0.quantity', 2);
    $f['sale']->refunds()->update(['amount' => 36000]);
    $this->getJson($path)->assertOk()->assertJsonPath('data.status', 'refunded');
    $f['sale']->update(['voided_at' => now(), 'void_reason' => 'Duplicate']);
    $this->getJson($path)->assertOk()->assertJsonPath('data.status', 'voided');
    $this->getJson('/api/partner/v1/sales-summary'.partnerWindow($f['branch']->id))->assertOk()->assertJsonPath('data.total_sales', 0);
});

test('partner refuses foreign outlet transaction and unbounded or invalid date windows', function () {
    $f = partnerFixture();
    $other = User::factory()->create(['role' => 'owner']);
    $foreign = Branch::create(['user_id' => $other->id, 'name' => 'Secret', 'code' => 'FOREIGN']);
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/v1/transactions'.partnerWindow($foreign->id))->assertNotFound();
    $this->getJson('/api/partner/v1/transactions/999999')->assertNotFound();
    $this->getJson('/api/partner/v1/transactions')->assertUnprocessable();
    foreach ([['2026-11-13', '2026-11-14'], ['2026-11-13T00:00:00+08:00', '2026-11-12T00:00:00+08:00'], ['2026-01-01T00:00:00Z', '2026-11-13T00:00:00Z']] as [$from, $to]) {
        $this->getJson('/api/partner/v1/transactions?'.http_build_query(['outlet_id' => $f['branch']->id, 'from' => $from, 'to' => $to]))->assertUnprocessable();
    }
    $this->getJson('/api/partner/v1/products?per_page=101')->assertUnprocessable();
});

test('sandbox credentials never read production and production credentials never read sandbox', function () {
    $f = partnerFixture();
    [$sandbox, $plain, $client] = PartnerApiKey::issue($f['owner'], 'Sandbox', true, now()->addDay());
    $this->withToken($plain)->withHeader('X-Client-Key', $client)->getJson('/api/partner/v1/outlets')->assertForbidden();
    $this->getJson('/api/partner/sandbox/v1/outlets')->assertOk()->assertJsonPath('data.0.id', 900001)->assertJsonPath('meta.sandbox', true);
    $this->getJson('/api/partner/sandbox/v1/transactions'.partnerWindow(900001))->assertOk()->assertJsonCount(3, 'data');
    $this->getJson('/api/partner/sandbox/v1/transactions/'.$f['sale']->id)->assertNotFound();
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/sandbox/v1/outlets')->assertForbidden();
});

test('expired and revoked keys fail and API keys cannot authorize POS writes', function () {
    $f = partnerFixture();
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->postJson('/api/mobile/v1/checkout', [])->assertUnauthorized();
    $f['key']->update(['expires_at' => now()->subSecond()]);
    $this->getJson('/api/partner/v1/outlets')->assertUnauthorized();
    $f['key']->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
    $this->getJson('/api/partner/v1/outlets')->assertUnauthorized();
});

test('partner rate limit is shared across routes and returns retry headers', function () {
    $f = partnerFixture();
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client']);
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/partner/v1/outlets')->assertOk();
    }
    $this->getJson('/api/partner/v1/products')->assertStatus(429)->assertHeader('Retry-After');
});

test('owner can issue and revoke partner keys through authenticated settings', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $response = $this->actingAs($owner)->postJson('/settings/partner-keys', ['name' => 'ERP', 'sandbox' => true, 'expires_in_days' => 30])->assertCreated();
    expect($response->json('token'))->toStartWith('mava_test_');
    $id = $response->json('data.id');
    $this->getJson('/settings/partner-keys')->assertOk()->assertJsonMissingPath('data.0.token_hash');
    $this->deleteJson('/settings/partner-keys/'.$id)->assertNoContent();
    expect(PartnerApiKey::findOrFail($id)->revoked_at)->not->toBeNull();
});

test('partner pagination is stable and empty pages retain metadata', function () {
    $f = partnerFixture();
    Branch::create(['user_id' => $f['owner']->id, 'name' => 'Outlet B', 'code' => 'PARTNER-B']);
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/v1/outlets?per_page=1&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Outlet B')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/partner/v1/outlets?per_page=1&page=3')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 2);
});

test('partner cannot retrieve another merchant sale even when IDs are known', function () {
    $f = partnerFixture();
    $other = User::factory()->create(['role' => 'owner']);
    $branch = Branch::create(['user_id' => $other->id, 'name' => 'Foreign', 'code' => 'SALE-FOREIGN']);
    $f['sale']->update(['branch_id' => $branch->id]);
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/v1/transactions/'.$f['sale']->id)->assertNotFound();
});

test('variant snapshots survive subsequent catalog edits and variant deletion', function () {
    $f = partnerFixture();
    $variant = $f['product']->variants()->create(['name' => 'Besar', 'sku' => 'BIG', 'sell_price' => 5000, 'attributes' => ['size' => 'L']]);
    $f['sale']->items()->delete();
    $item = $f['sale']->items()->create(['product_id' => $f['product']->id, 'product_variant_id' => $variant->id, 'item_type' => 'variant', 'name' => 'Kopi Besar', 'quantity' => 1, 'unit_price' => 25000, 'line_total' => 25000]);
    $variant->update(['name' => 'Extra Besar', 'attributes' => ['size' => 'XL']]);
    $variant->delete();
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/v1/transactions/'.$f['sale']->id)->assertOk()->assertJsonPath('data.items.0.variant.name', 'Besar')->assertJsonPath('data.items.0.variant.attributes.size', 'L');
});

test('updated_since includes refund changes even when sale itself was not modified', function () {
    $f = partnerFixture();
    $f['sale']->forceFill(['updated_at' => '2026-11-13 00:00:00'])->saveQuietly(['timestamps' => false]);
    $query = '/api/partner/v1/transactions'.partnerWindow($f['branch']->id).'&updated_since='.urlencode('2026-11-14T00:00:00Z');
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson($query)->assertOk()->assertJsonCount(0, 'data');
    $refund = $f['sale']->refunds()->create(['tenant_id' => $f['owner']->id, 'branch_id' => $f['branch']->id, 'user_id' => $f['owner']->id, 'refund_number' => 'REF-POLL', 'client_refund_id' => 'poll', 'request_hash' => 'hash', 'amount' => 1000, 'refund_method' => 'cash', 'reason' => 'Poll', 'refunded_at' => now()]);
    $refund->forceFill(['updated_at' => '2026-11-14 00:00:01'])->saveQuietly(['timestamps' => false]);
    $this->getJson($query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.updated_at', '2026-11-14T08:00:01+08:00');
    $this->getJson('/api/partner/v1/transactions'.partnerWindow($f['branch']->id).'&status=completed')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/partner/v1/transactions'.partnerWindow($f['branch']->id).'&status=partially_refunded')->assertOk()->assertJsonCount(1, 'data');
});

test('key management cannot revoke another merchants key and cashier cannot create keys', function () {
    $f = partnerFixture();
    $other = User::factory()->create(['role' => 'owner']);
    $this->actingAs($other)->deleteJson('/settings/partner-keys/'.$f['key']->id)->assertNotFound();
    $cashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $f['owner']->id, 'branch_id' => $f['branch']->id]);
    $this->actingAs($cashier)->postJson('/settings/partner-keys', ['name' => 'No', 'sandbox' => false, 'expires_in_days' => 90])->assertForbidden();
});

test('partner payment amounts describe receipts rather than tendered change and merchant deductions', function () {
    $f = partnerFixture();
    $f['sale']->payments()->delete();
    $f['sale']->update(['payment_method' => 'cash', 'paid_amount' => 50000, 'change_amount' => 14000]);
    $path = '/api/partner/v1/transactions/'.$f['sale']->id;
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->getJson($path)->assertOk()->assertJsonPath('data.payments.0.amount', 36000)->assertJsonPath('data.received_amount', 36000)->assertJsonPath('data.merchant_deduction', 0);
    $f['sale']->update(['payment_method' => 'online_merchant', 'online_merchant' => 'grabfood', 'paid_amount' => 30000, 'change_amount' => 0]);
    $this->getJson($path)->assertOk()->assertJsonPath('data.payments.0.amount', 30000)->assertJsonPath('data.received_amount', 30000)->assertJsonPath('data.merchant_deduction', 6000)->assertJsonPath('data.online_merchant', 'grabfood');
});
