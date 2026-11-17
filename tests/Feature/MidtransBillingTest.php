<?php

use App\Models\Billing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-test', 'services.midtrans.client_key' => 'SB-Mid-client-test', 'services.midtrans.is_production' => false]);
    Http::preventStrayRequests();
});

function midtransBilling(array $attributes = []): Billing
{
    $owner = User::factory()->create(['role' => 'owner']);

    return Billing::create(array_replace(['user_id' => $owner->id, 'invoice_number' => 'INV-MID-001', 'customer_name' => 'Toko Uji', 'title' => 'Basic Plan', 'amount' => 199000, 'total_payment' => 199000, 'payment_provider' => 'midtrans', 'payment_method' => 'snap', 'payment_status' => 'pending', 'provider_payload' => ['midtrans_environment' => 'sandbox', 'subscription' => ['plan_slug' => 'basic', 'months' => 1, 'period_ends_at' => now()->addMonth()->toDateString()]]], $attributes));
}

function midtransStatus(Billing $billing, array $overrides = []): array
{
    return array_replace(['order_id' => $billing->invoice_number, 'transaction_id' => 'midtrans-test-id', 'status_code' => '200', 'gross_amount' => number_format($billing->amount, 2, '.', ''), 'currency' => 'IDR', 'transaction_status' => 'settlement', 'payment_type' => 'qris', 'settlement_time' => '2026-11-16 10:00:00'], $overrides);
}

function midtransSigned(array $payload): array
{
    $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('services.midtrans.server_key'));

    return $payload;
}

test('billing checkout uses Midtrans sandbox Snap redirect and never exposes credentials', function () {
    Http::fake(['https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'snap-test-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-test-token'], 201)]);
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner)->post('/billings', ['plan_slug' => 'basic', 'billing_cycle' => 'monthly'])->assertRedirect('/billings')->assertSessionHasNoErrors();
    $billing = Billing::firstOrFail();
    expect($billing->payment_provider)->toBe('midtrans')->and($billing->amount)->toBe(199000)->and($billing->payment_url)->toBe('https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-test-token');
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-test:')) && $r['transaction_details']['gross_amount'] === 199000 && $r['transaction_details']['order_id'] === $billing->invoice_number && $r['callbacks']['finish'] === route('billings'));
    $this->get('/billings')->assertOk()->assertSee('Bayar via Midtrans')->assertDontSee('SB-Mid-server-test')->assertDontSee('SUMOPOD_API_KEY');
});

test('unconfigured Midtrans fails closed', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    config(['services.midtrans.server_key' => '']);
    $this->actingAs($owner)->post('/billings', ['plan_slug' => 'basic', 'billing_cycle' => 'monthly'])->assertSessionHasErrors('midtrans');
    expect(Billing::count())->toBe(0);
});

test('gateway errors keep a failed local invoice without leaking details', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner);
    Http::fake(['*' => Http::response(['error_messages' => ['Internal secret detail']], 500)]);
    $this->post('/billings', ['plan_slug' => 'basic', 'billing_cycle' => 'monthly'])->assertSessionHasErrors('midtrans');
    expect(Billing::firstOrFail()->payment_status)->toBe('failed');
    expect(session('errors')->first('midtrans'))->not->toContain('Internal secret detail');
});

test('notification requires signature and independently verifies current remote status', function () {
    $billing = midtransBilling();
    $payload = midtransStatus($billing);
    $this->postJson('/midtrans/notification', $payload)->assertUnauthorized();
    Http::assertNothingSent();
    Http::fake(['*' => Http::response($payload)]);
    $this->postJson('/midtrans/notification', midtransSigned($payload))->assertOk()->assertJsonPath('payment_status', 'completed');
    expect($billing->fresh()->paid_at)->not->toBeNull()->and($billing->fresh()->payment_method)->toBe('qris');
    $paidAt = $billing->fresh()->paid_at->toISOString();
    $this->postJson('/midtrans/notification', midtransSigned($payload))->assertOk();
    expect($billing->fresh()->paid_at->toISOString())->toBe($paidAt);
});

test('signed notification cannot override actual pending or fraud challenged transactions', function (array $remote) {
    $billing = midtransBilling();
    $claimed = midtransStatus($billing);
    Http::fake(['*' => Http::response(midtransStatus($billing, $remote))]);
    $this->postJson('/midtrans/notification', midtransSigned($claimed))->assertOk()->assertJsonPath('payment_status', 'pending');
    expect($billing->fresh()->paid_at)->toBeNull();
})->with([
    [['transaction_status' => 'pending', 'status_code' => '201']],
    [['transaction_status' => 'capture', 'fraud_status' => 'challenge', 'payment_type' => 'credit_card']],
    [['transaction_status' => 'settlement', 'fraud_status' => 'deny']],
]);

test('notification rejects wrong amounts unknown orders provider mismatches and status lookup outages', function () {
    $billing = midtransBilling();
    $this->postJson('/midtrans/notification', midtransSigned(midtransStatus($billing, ['gross_amount' => '199000.50'])))->assertUnprocessable();
    $this->postJson('/midtrans/notification', midtransSigned(midtransStatus($billing, ['order_id' => 'UNKNOWN'])))->assertNotFound();
    Http::fake(['*' => Http::sequence()->push(midtransStatus($billing, ['gross_amount' => '1.00']))->push([], 503)]);
    $this->postJson('/midtrans/notification', midtransSigned(midtransStatus($billing)))->assertUnprocessable();
    $this->postJson('/midtrans/notification', midtransSigned(midtransStatus($billing)))->assertStatus(503);
    expect($billing->fresh()->paid_at)->toBeNull();
    $this->postJson('/sumopod/webhook', ['event_type' => 'payment.completed', 'data' => ['order_id' => $billing->invoice_number, 'net_amount' => 199000]])->assertNotFound();
});

test('manual status refresh verifies remotely even after local expiry and preserves tenant isolation', function () {
    $billing = midtransBilling(['expires_at' => now()->subDay()]);
    Http::fake(['*' => Http::response(midtransStatus($billing))]);
    $owner = User::findOrFail($billing->user_id);
    $this->actingAs($owner)->from('/billings')->post('/billings/'.$billing->id.'/refresh')->assertRedirect('/billings');
    expect($billing->fresh()->payment_status)->toBe('completed');
    $other = User::factory()->create(['role' => 'owner']);
    $this->actingAs($other)->post('/billings/'.$billing->id.'/refresh')->assertNotFound();
});

test('a valid signature for pending data never changes refund finality or the original paid timestamp', function () {
    $billing = midtransBilling(['payment_status' => 'completed', 'paid_at' => now()->subDay()]);
    $paid = $billing->paid_at->toISOString();
    $states = [['transaction_status' => 'pending', 'status_code' => '201'], ['transaction_status' => 'refund'], ['transaction_status' => 'settlement']];
    $sequence = Http::sequence();
    foreach ($states as $state) {
        $sequence->push(midtransStatus($billing, $state));
    }
    Http::fake(['*' => $sequence]);
    foreach ($states as $i => $state) {
        $payload = midtransStatus($billing, $state);
        $this->postJson('/midtrans/notification', midtransSigned($payload))->assertOk()->assertJsonPath('payment_status', $i === 0 ? 'completed' : 'refunded');
        expect($billing->fresh()->paid_at->toISOString())->toBe($paid);
    }
});

test('Snap has not chosen a payment method yet returns pending on refresh instead of a gateway error', function (int $httpStatus) {
    $billing = midtransBilling();
    Http::fake(['*' => Http::response(['status_code' => '404', 'status_message' => 'The requested resource is not found'], $httpStatus)]);
    $this->actingAs(User::findOrFail($billing->user_id))->from('/billings')->post('/billings/'.$billing->id.'/refresh')->assertRedirect('/billings')->assertSessionHasNoErrors();
    expect($billing->fresh()->payment_status)->toBe('pending');
})->with([200, 404]);

test('unused Snap sessions expire locally only when no remote payment exists', function () {
    $billing = midtransBilling(['expires_at' => now()->subMinute()]);
    Http::fake(['*' => Http::response(['status_code' => '404'], 200)]);
    $this->actingAs(User::findOrFail($billing->user_id))->from('/billings')->post('/billings/'.$billing->id.'/refresh')->assertRedirect('/billings');
    expect($billing->fresh()->payment_status)->toBe('expired')->and($billing->fresh()->paid_at)->toBeNull();
});

test('returning from Midtrans checkout reconciles a locally pending invoice before showing subscription status', function () {
    $billing = midtransBilling();
    Http::fake(['*' => Http::response(midtransStatus($billing))]);
    $this->actingAs(User::findOrFail($billing->user_id))
        ->followingRedirects()->get('/billings?'.http_build_query(['order_id' => $billing->invoice_number, 'transaction_status' => 'pending']))
        ->assertOk()->assertViewHas('currentSubscription', fn ($subscription) => $subscription['active'] === true);
    expect($billing->fresh()->payment_status)->toBe('completed');
    Http::assertSentCount(1);
});

test('return parameters do not grant payment or cross merchant boundaries', function () {
    $billing = midtransBilling();
    Http::fake(['*' => Http::response(midtransStatus($billing, ['transaction_status' => 'pending', 'status_code' => '201']))]);
    $this->actingAs(User::findOrFail($billing->user_id))->followingRedirects()->get('/billings?'.http_build_query(['order_id' => $billing->invoice_number, 'transaction_status' => 'settlement']))->assertOk();
    expect($billing->fresh()->paid_at)->toBeNull();
    $other = User::factory()->create(['role' => 'owner']);
    $this->actingAs($other)->get('/billings?order_id='.$billing->invoice_number)->assertNotFound();
    Http::assertSentCount(1);
});

test('automatic refresh returns verified JSON without gateway credentials and rejects another merchant', function () {
    $billing = midtransBilling();
    Http::fake(['*' => Http::response(midtransStatus($billing))]);
    $url = '/billings/'.$billing->id.'/refresh';
    $this->actingAs(User::findOrFail($billing->user_id))->postJson($url)->assertOk()->assertJsonPath('payment_status', 'completed')->assertJsonMissingPath('provider_payload')->assertJsonMissingPath('signature_key');
    $this->actingAs(User::factory()->create(['role' => 'owner']))->postJson($url)->assertNotFound();
    Http::assertSentCount(1);
});

test('checkout return and automatic refresh handle gateway outages without assuming a successful payment', function () {
    $billing = midtransBilling();
    Http::fake(['*' => Http::response([], 503)]);
    $this->actingAs(User::findOrFail($billing->user_id))->followingRedirects()->get('/billings?order_id='.$billing->invoice_number)->assertOk();
    $this->postJson('/billings/'.$billing->id.'/refresh')->assertStatus(503);
    expect($billing->fresh()->paid_at)->toBeNull();
});

test('checkout returns and JSON refresh share one status verification rate limit', function () {
    $billing = midtransBilling();
    Http::fake(['*' => Http::response(midtransStatus($billing, ['transaction_status' => 'pending', 'status_code' => '201']))]);
    $this->actingAs(User::findOrFail($billing->user_id));
    for ($i = 0; $i < 15; $i++) {
        $this->followingRedirects()->get('/billings?order_id='.$billing->invoice_number)->assertOk();
        $this->postJson('/billings/'.$billing->id.'/refresh')->assertOk();
    }
    $this->get('/billings?order_id='.$billing->invoice_number)->assertStatus(429)->assertHeader('Retry-After');
    $this->postJson('/billings/'.$billing->id.'/refresh')->assertStatus(429)->assertHeader('Retry-After');
    Http::assertSentCount(30);
});

test('Snap js uses the environment client key and only exposes tokens for owned pending invoices', function () {
    config(['services.midtrans.client_key' => 'SB-Mid-client-test']);
    $billing = midtransBilling(['provider_payload' => ['midtrans_environment' => 'sandbox', 'create' => ['token' => 'owned-pending-token']]]);
    $this->actingAs(User::findOrFail($billing->user_id))->get('/billings')->assertOk()
        ->assertSee('https://app.sandbox.midtrans.com/snap/snap.js', false)
        ->assertSee('data-client-key="SB-Mid-client-test"', false)
        ->assertViewHas('billings', fn ($rows) => $rows->first()['snapToken'] === 'owned-pending-token')
        ->assertDontSee('SB-Mid-server-test');
    $billing->update(['payment_status' => 'completed', 'paid_at' => now()]);
    $this->get('/billings')->assertOk()->assertViewHas('billings', fn ($rows) => $rows->first()['snapToken'] === null);
    $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/billings')->assertOk()->assertDontSee('owned-pending-token');
});

test('a missing client key keeps hosted checkout available without loading Snap js', function () {
    config(['services.midtrans.client_key' => '']);
    $billing = midtransBilling(['payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test', 'provider_payload' => ['midtrans_environment' => 'sandbox', 'create' => ['token' => 'test-token']]]);
    $this->actingAs(User::findOrFail($billing->user_id))->get('/billings')->assertOk()
        ->assertDontSee('https://app.sandbox.midtrans.com/snap/snap.js', false)
        ->assertDontSee('data-client-key=', false)
        ->assertSee('Buka Halaman Pembayaran');
});

test('new invoices reopen as a Snap popup once after the checkout form redirects', function () {
    config(['services.midtrans.client_key' => 'SB-Mid-client-test']);
    Http::fake(['*' => Http::response(['token' => 'new-popup-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/new-popup-token'], 201)]);
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner)->post('/billings', ['plan_slug' => 'basic', 'billing_cycle' => 'monthly'])->assertRedirect('/billings')->assertSessionHas('midtrans_snap_invoice');
    $invoice = Billing::firstOrFail();
    $this->get('/billings')->assertOk()->assertViewHas('autoSnapCheckout', fn ($checkout) => $checkout['token'] === 'new-popup-token' && $checkout['verifyUrl'] === route('billings', ['order_id' => $invoice->invoice_number]));
    $this->get('/billings')->assertOk()->assertViewHas('autoSnapCheckout', null);
});

test('provided Client Key is passed unchanged while server configuration selects the Snap js environment', function () {
    config(['services.midtrans.client_key' => 'Mid-client-opaque-value']);
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner)->get('/billings')->assertOk()
        ->assertSee('https://app.sandbox.midtrans.com/snap/snap.js', false)
        ->assertSee('data-client-key="Mid-client-opaque-value"', false);
});
