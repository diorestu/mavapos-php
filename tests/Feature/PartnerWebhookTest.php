<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PartnerApiKey;
use App\Models\PartnerWebhookDelivery;
use App\Models\PosSale;
use App\Models\User;
use App\Services\Partner\WebhookDestination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function partnerWebhookFixture(): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $branch = Branch::create(['user_id' => $owner->id, 'code' => 'WH-OUTLET', 'name' => 'Webhook Outlet']);
    $shift = CashierShift::create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    [$key, $plain, $client] = PartnerApiKey::issue($owner, 'Webhook', false, now()->addDay());
    config(['partner.webhook_hosts' => ['hooks.example.org']]);
    $destination = Mockery::mock(WebhookDestination::class)->makePartial();
    $destination->shouldReceive('addresses')->andReturn(['93.184.216.34']);
    app()->instance(WebhookDestination::class, $destination);

    return compact('owner', 'branch', 'shift', 'key', 'plain', 'client');
}

function partnerWebhookSale(array $f, string $invoice = 'WH-001'): PosSale
{
    return PosSale::create(['branch_id' => $f['branch']->id, 'user_id' => $f['owner']->id, 'cashier_shift_id' => $f['shift']->id, 'invoice_number' => $invoice, 'payment_method' => 'cash', 'subtotal' => 10000, 'discount' => 0, 'total' => 10000, 'paid_amount' => 10000, 'sold_at' => now()]);
}

test('webhook registration rejects internal unapproved or non HTTPS destinations and keeps secret hidden', function () {
    $f = partnerWebhookFixture();
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client']);
    foreach (['http://hooks.example.org/sale', 'https://127.0.0.1/sale', 'https://evil.example/sale', 'https://user:password@hooks.example.org/sale', 'https://hooks.example.org:8443/sale'] as $url) {
        $this->postJson('/api/partner/v1/webhook', ['url' => $url])->assertUnprocessable();
    }
    $created = $this->postJson('/api/partner/v1/webhook', ['url' => 'https://hooks.example.org/sales'])->assertCreated();
    expect($created->json('signing_secret'))->toStartWith('whsec_');
    $this->getJson('/api/partner/v1/webhook')->assertOk()->assertJsonMissingPath('data.signing_secret');
    $this->postJson('/api/partner/v1/webhook', ['url' => 'https://hooks.example.org/other'])->assertStatus(409);
});

test('transaction creation enqueues one signed event and retries failures without blocking checkout', function () {
    $f = partnerWebhookFixture();
    $secret = $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->postJson('/api/partner/v1/webhook', ['url' => 'https://hooks.example.org/sales'])->assertCreated()->json('signing_secret');
    Http::fake(['*' => Http::sequence()->push('', 500)->push('', 204)]);
    $sale = partnerWebhookSale($f);
    $sale->update(['discount' => 100]);
    Http::assertNothingSent();
    expect(PartnerWebhookDelivery::count())->toBe(1);
    $this->artisan('partner:webhooks:send')->assertSuccessful();
    $delivery = PartnerWebhookDelivery::firstOrFail();
    expect($delivery->attempts)->toBe(1)->and($delivery->delivered_at)->toBeNull();
    $delivery->update(['available_at' => now()->subSecond()]);
    $this->artisan('partner:webhooks:send')->assertSuccessful();
    expect($delivery->fresh()->delivered_at)->not->toBeNull();
    Http::assertSentCount(2);
    Http::assertSent(function ($request) use ($secret, $sale) {
        $timestamp = $request->header('X-Mava-Timestamp')[0];

        return $request->header('X-Mava-Signature')[0] === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $secret)
            && $request['data']['transaction_id'] === $sale->id && $request['event'] === 'transaction.created';
    });
});

test('webhook outbox rolls back with sale and excludes foreign tenant sales', function () {
    $f = partnerWebhookFixture();
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->postJson('/api/partner/v1/webhook', ['url' => 'https://hooks.example.org/sales'])->assertCreated();
    DB::beginTransaction();
    partnerWebhookSale($f);
    expect(PartnerWebhookDelivery::count())->toBe(1);
    DB::rollBack();
    expect(PartnerWebhookDelivery::count())->toBe(0);
    $other = User::factory()->create(['role' => 'owner']);
    $foreign = Branch::create(['user_id' => $other->id, 'name' => 'Foreign', 'code' => 'WH-FOREIGN']);
    partnerWebhookSale(array_replace($f, ['owner' => $other, 'branch' => $foreign]));
    expect(PartnerWebhookDelivery::count())->toBe(0);
});

test('revoking key cancels pending deliveries and exhausted deliveries stop retrying', function () {
    $f = partnerWebhookFixture();
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->postJson('/api/partner/v1/webhook', ['url' => 'https://hooks.example.org/sales'])->assertCreated();
    Http::fake(['*' => Http::response('', 500)]);
    partnerWebhookSale($f);
    $delivery = PartnerWebhookDelivery::firstOrFail();
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $delivery->refresh()->update(['available_at' => now()->subSecond()]);
        $this->artisan('partner:webhooks:send')->assertSuccessful();
    }
    Http::assertSentCount(5);
    expect($delivery->fresh()->failed_at)->not->toBeNull();
    partnerWebhookSale($f, 'WH-002');
    $f['key']->update(['revoked_at' => now()]);
    $this->artisan('partner:webhooks:send')->assertSuccessful();
    Http::assertSentCount(5);
});

test('sandbox can generate a signed test event but never sends HTTP or queues real sales', function () {
    $f = partnerWebhookFixture();
    [$key, $plain, $client] = PartnerApiKey::issue($f['owner'], 'Sandbox', true, now()->addDay());
    Http::fake();
    $secret = $this->withToken($plain)->withHeader('X-Client-Key', $client)->postJson('/api/partner/sandbox/v1/webhook', ['url' => 'https://hooks.example.org/sandbox'])->assertCreated()->json('signing_secret');
    $response = $this->postJson('/api/partner/sandbox/v1/webhook/test')->assertOk();
    expect($response->json('headers.X-Mava-Signature'))->toBe('sha256='.hash_hmac('sha256', $response->json('headers.X-Mava-Timestamp').'.'.$response->json('body'), $secret));
    partnerWebhookSale($f);
    expect(PartnerWebhookDelivery::count())->toBe(0);
    $this->artisan('partner:webhooks:send')->assertSuccessful();
    Http::assertNothingSent();
});

test('webhook sender refuses private DNS resolution even for an allowed hostname', function () {
    $f = partnerWebhookFixture();
    $this->withToken($f['plain'])->withHeader('X-Client-Key', $f['client'])->postJson('/api/partner/v1/webhook', ['url' => 'https://hooks.example.org/sales'])->assertCreated();
    partnerWebhookSale($f);
    $destination = Mockery::mock(WebhookDestination::class)->makePartial();
    $destination->shouldReceive('addresses')->andReturn(['127.0.0.1']);
    app()->instance(WebhookDestination::class, $destination);
    Http::fake();
    $this->artisan('partner:webhooks:send')->assertSuccessful();
    Http::assertNothingSent();
    expect(PartnerWebhookDelivery::firstOrFail()->attempts)->toBe(1);
});
