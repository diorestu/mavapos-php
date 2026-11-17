<?php

use App\Models\Billing;
use App\Models\GatewayPayment;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\WalletEntry;
use App\Services\Partner\ApiResource;
use App\Services\PosGatewayService;
use App\Services\WalletService;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function posGatewayFixture($test, bool $failCreate = false): array
{
    config(['services.midtrans.server_key' => 'SB-Mid-server-test', 'services.midtrans.client_key' => 'Mid-client-test', 'services.midtrans.is_production' => false]);
    Http::preventStrayRequests();
    $owner = User::factory()->create(['role' => 'owner']);
    $test->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $product = Product::create(['user_id' => $owner->id, 'sku' => 'GATEWAY-ITEM', 'name' => 'Produk Gateway', 'sell_price' => 20000, 'stock_mode' => 'inventory', 'stock' => 5]);
    $inventory = app(BranchInventoryManager::class)->forProduct($branch->id, $product);
    $inventory->update(['stock' => 5]);
    $test->postJson(route('pos.shift.start'))->assertOk();
    $body = ['client_transaction_id' => (string) Str::uuid(), 'items' => [['id' => 'product-GATEWAY-ITEM', 'quantity' => 2]], 'discount' => 0];
    Http::fake(['https://app.sandbox.midtrans.com/snap/v1/transactions' => $failCreate ? Http::response([], 500) : Http::response(['token' => 'pos-snap-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/pos-snap-token'], 201)]);

    return compact('owner', 'branch', 'product', 'inventory', 'body');
}

function posGatewayRemote(GatewayPayment $p, array $overrides = []): array
{
    return array_replace(['order_id' => $p->order_id, 'transaction_id' => 'pos-mid-remote', 'transaction_status' => 'settlement', 'status_code' => '200', 'fraud_status' => 'accept', 'gross_amount' => number_format($p->amount, 2, '.', ''), 'currency' => 'IDR', 'payment_type' => 'qris', 'settlement_time' => '2026-11-16 10:00:00'], $overrides);
}

test('new gateway checkout reserves stock but does not count unpaid revenue or duplicate requests', function () {
    $f = posGatewayFixture($this);
    $first = $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    $this->get(route('pos.gateway.show', $p))->assertOk()->assertSee('Menunggu pembayaran');
    $sale = $p->sale;
    expect($sale->payment_status)->toBe('pending')->and($sale->paid_amount)->toBe(0)->and($f['inventory']->fresh()->stock)->toBe(3)->and(PosSale::active()->count())->toBe(0)->and(WalletEntry::count())->toBe(0);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertOk()->assertJsonPath('id', $p->id);
    $changed = $f['body'];
    $changed['items'][0]['quantity'] = 1;
    $this->postJson('/pos/gateway-checkout', $changed)->assertStatus(409);
    Http::assertSentCount(1);
});

test('verified settlement pays the sale credits its wallet once and never mixes subscription receipts', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::response(posGatewayRemote($p))]);
    app(PosGatewayService::class)->sync($p);
    app(PosGatewayService::class)->sync($p);
    expect($p->sale->fresh()->payment_status)->toBe('paid')->and(PosSale::active()->sum('total'))->toBe(40000)->and(WalletEntry::where('bucket', 'unreconciled')->sum('amount'))->toBe(40000)->and(Billing::count())->toBe(0);
    expect($f['inventory']->fresh()->stock)->toBe(3);
});

test('capture can pay a sale but wallet funds wait for settlement', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::response(posGatewayRemote($p, ['transaction_status' => 'capture', 'payment_type' => 'credit_card']))]);
    app(PosGatewayService::class)->sync($p);
    expect($p->sale->fresh()->payment_status)->toBe('paid')->and(WalletEntry::count())->toBe(0);
});

test('expired unpaid payments restore reserved stock once even after the cashier shift closes', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    $p->sale->shift->update(['closed_at' => now()]);
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::response(posGatewayRemote($p, ['transaction_status' => 'expire']))]);
    app(PosGatewayService::class)->sync($p);
    app(PosGatewayService::class)->sync($p);
    expect($p->sale->fresh()->voided_at)->not->toBeNull()->and($f['inventory']->fresh()->stock)->toBe(5)->and(PosSale::active()->count())->toBe(0)->and(WalletEntry::count())->toBe(0);
});

test('gateway payment notifications verify signatures amounts and source before wallet postings', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    $data = posGatewayRemote($p);
    $this->postJson('/midtrans/notification', $data)->assertUnauthorized();
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::response($data)]);
    $data['signature_key'] = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].config('services.midtrans.server_key'));
    $this->postJson('/midtrans/notification', $data)->assertOk();
    expect(WalletEntry::where('bucket', 'unreconciled')->sum('amount'))->toBe(40000);
    $other = User::factory()->create(['role' => 'owner']);
    $this->actingAs($other)->get('/pos/gateway-payments/'.$p->id)->assertNotFound();
    $this->postJson('/sumopod/webhook', ['order_id' => $p->order_id, 'status' => 'paid', 'amount' => 40000])->assertNotFound();
});

test('gateway checkout is online direct and cannot use offline or loyalty payloads', function () {
    $f = posGatewayFixture($this);
    foreach ([['client_created_at' => now()->toISOString()], ['loyalty_stamp' => true], ['sales_channel' => 'online'], ['payments' => [['method' => 'cash', 'amount' => 1]]]] as $invalid) {
        $this->postJson('/pos/gateway-checkout', array_replace($f['body'], $invalid))->assertUnprocessable();
    }
    expect(GatewayPayment::count())->toBe(0);
});

test('gateway checkout cannot be coerced into an already paid online merchant sale', function () {
    $f = posGatewayFixture($this);
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson('/pos/gateway-checkout', array_replace($f['body'], ['online_merchant' => 'grabfood', 'paid_amount' => 40000]))->assertUnprocessable();
    expect(PosSale::count())->toBe(0)->and(GatewayPayment::count())->toBe(0)->and($f['inventory']->fresh()->stock)->toBe(5);
});

test('authoritative settlement reversals remove withdrawable funds without restocking fulfilled goods', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::sequence()->push(posGatewayRemote($p))->push(posGatewayRemote($p, ['transaction_status' => 'deny']))->push(posGatewayRemote($p, ['transaction_status' => 'deny']))]);
    $gateway = app(PosGatewayService::class);
    $wallet = app(WalletService::class);
    $gateway->sync($p);
    $admin = User::factory()->create(['role' => 'superadmin']);
    $wallet->reconcileFee($p, 1000, 'SETTLEMENT-REPORT', $admin);
    $gateway->sync($p);
    $gateway->sync($p);
    expect($wallet->balances($wallet->account($f['owner']->id, 'sandbox'))['available'])->toBe(-1000)
        ->and($p->fresh()->refunded_amount)->toBe(40000)->and($p->fresh()->status)->toBe('reversed')
        ->and($f['inventory']->fresh()->stock)->toBe(3)->and($p->sale->fresh()->voided_at)->toBeNull()
        ->and($p->sale->refunds()->sum('amount'))->toBe(40000);
});

test('capture cancellation reverses financial reporting without inventing settled wallet funds', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::sequence()->push(posGatewayRemote($p, ['transaction_status' => 'capture', 'payment_type' => 'credit_card']))->push(posGatewayRemote($p, ['transaction_status' => 'cancel', 'payment_type' => 'credit_card']))]);
    app(PosGatewayService::class)->sync($p);
    app(PosGatewayService::class)->sync($p);
    expect(WalletEntry::count())->toBe(0)->and($p->fresh()->status)->toBe('reversed')->and($p->sale->refunds()->sum('amount'))->toBe(40000)->and($f['inventory']->fresh()->stock)->toBe(3);
});

test('signed gateway expiry restores the correct tenant inventory even with another user session attached', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    $data = posGatewayRemote($p, ['transaction_status' => 'expire']);
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::response($data)]);
    $data['signature_key'] = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].config('services.midtrans.server_key'));
    $other = User::factory()->create(['role' => 'owner']);
    $this->actingAs($other)->postJson('/midtrans/notification', $data)->assertOk();
    expect($f['inventory']->fresh()->stock)->toBe(5)->and(auth()->id())->toBe($other->id);
});

test('scheduled reconciliation releases an unused expired session and keeps a failed request idempotent', function () {
    $f = posGatewayFixture($this, true);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    expect($p->creation_status)->toBe('failed');
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertOk();
    $p->update(['expires_at' => now()->subMinute()]);
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::response(['status_code' => '404'], 200)]);
    $this->artisan('gateway:reconcile')->assertSuccessful();
    expect($f['inventory']->fresh()->stock)->toBe(5)->and($p->sale->fresh()->payment_status)->toBe('canceled');
});

test('pending gateway orders do not expose receipts or revenue as already received to partners', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    $resource = ApiResource::transaction($p->sale->load('items', 'payments', 'refunds.items'));
    expect($resource['status'])->toBe('pending')->and($resource['received_amount'])->toBe(0)->and($resource['net_total'])->toBe(0)->and($resource['payments'])->toBe([]);
});

test('a request interrupted during gateway creation becomes reachable after expiry without creating another charge', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    $p->update(['creation_status' => 'creating', 'snap_token' => null, 'payment_url' => null, 'expires_at' => now()->subMinute()]);
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::response(['status_code' => '404'], 200)]);
    $this->artisan('gateway:reconcile')->assertSuccessful();
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertOk()->assertJsonPath('id', $p->id);
    expect(PosSale::count())->toBe(1)->and(GatewayPayment::count())->toBe(1)->and($p->fresh()->creation_status)->toBe('failed')->and($f['inventory']->fresh()->stock)->toBe(5);
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/snap/v1/transactions'));
});

test('checkout popup activation survives unrelated ajax requests consuming session flash data', function () {
    $f = posGatewayFixture($this);
    $response = $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    session()->forget('pos_gateway_open');
    $this->get($response->json('redirect_url'))->assertOk()->assertViewHas('autoOpen', true);
});

test('gateway funds require an actual merchant owner rather than an unassigned standalone staff account', function () {
    $f = posGatewayFixture($this);
    $f['owner']->update(['role' => 'admin']);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertUnprocessable();
    expect(GatewayPayment::count())->toBe(0)->and($f['inventory']->fresh()->stock)->toBe(5);
});

test('a verified chargeback removes credited funds even when the later fraud status is deny', function () {
    $f = posGatewayFixture($this);
    $this->postJson('/pos/gateway-checkout', $f['body'])->assertCreated();
    $p = GatewayPayment::firstOrFail();
    Http::fake(['https://api.sandbox.midtrans.com/v2/*/status' => Http::sequence()->push(posGatewayRemote($p))->push(posGatewayRemote($p, ['transaction_status' => 'chargeback', 'fraud_status' => 'deny']))]);
    $gateway = app(PosGatewayService::class);
    $gateway->sync($p);
    $gateway->sync($p);
    expect($p->fresh()->refunded_amount)->toBe(40000)->and(WalletEntry::where('bucket', 'unreconciled')->sum('amount'))->toBe(0)->and($p->sale->refunds()->sum('amount'))->toBe(40000);
});
