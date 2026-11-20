<?php

use App\Models\PartnerApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function partnerClientCredentials(): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    [$key, $token, $client] = PartnerApiKey::issue($owner, 'Accounting', false, now()->addDay());

    return compact('owner', 'key', 'token', 'client');
}

test('partner requests require the client key paired with the bearer token', function () {
    $f = partnerClientCredentials();
    $this->withToken($f['token'])->getJson('/api/partner/v1/outlets')->assertUnauthorized();
    $this->withHeader('X-Client-Key', 'incorrect')->getJson('/api/partner/v1/outlets')->assertUnauthorized();
    $this->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/v1/outlets')->assertOk();
    [, , $otherClient] = PartnerApiKey::issue($f['owner'], 'Another integration', false, now()->addDay());
    $this->withHeader('X-Client-Key', $otherClient)->getJson('/api/partner/v1/outlets')->assertUnauthorized();
    $this->withHeader('X-Client-Key', str_repeat('a', 201))->getJson('/api/partner/v1/outlets')->assertUnauthorized();
});

test('partner credential generation stores only hashes and does not disclose them through settings', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $response = $this->actingAs($owner)->postJson('/settings/partner-keys', ['name' => 'ERP', 'sandbox' => true, 'expires_in_days' => 30])->assertCreated();
    $client = $response->json('client_key');
    $token = $response->json('token');
    expect($client)->toBeString()->toStartWith('mava_client_');
    $key = PartnerApiKey::firstOrFail();
    expect($key->client_key_hash)->toBe(hash('sha256', $client))->and($key->token_hash)->toBe(hash('sha256', $token));
    expect($key->getRawOriginal('client_key_hash'))->not->toBe($client);
    $this->getJson('/settings/partner-keys')->assertOk()->assertJsonPath('data.0.client_key_configured', true)
        ->assertJsonMissingPath('data.0.client_key_hash')->assertJsonMissingPath('data.0.token_hash')
        ->assertJsonMissingPath('data.0.client_key')->assertJsonMissingPath('data.0.token');
    $this->get('/settings')->assertOk()->assertSee('X-Client-Key')->assertSee('Buat Pasangan Kredensial');
});

test('rotating a client key preserves bearer credentials while invalidating the old pair', function () {
    $f = partnerClientCredentials();
    $tokenHash = $f['key']->token_hash;
    $rotation = $this->actingAs($f['owner'])->postJson('/settings/partner-keys/'.$f['key']->id.'/client-key')->assertCreated();
    $newClient = $rotation->json('client_key');
    expect($newClient)->not->toBe($f['client'])->and($f['key']->fresh()->token_hash)->toBe($tokenHash);
    $this->withToken($f['token'])->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/v1/outlets')->assertUnauthorized();
    $this->withHeader('X-Client-Key', $newClient)->getJson('/api/partner/v1/outlets')->assertOk();
    $f['key']->update(['revoked_at' => now()]);
    $this->getJson('/api/partner/v1/outlets')->assertUnauthorized();
});

test('old bearer tokens fail closed until a client key is generated in settings', function () {
    $f = partnerClientCredentials();
    $f['key']->update(['client_key_hash' => null, 'client_key_prefix' => null]);
    $this->withToken($f['token'])->withHeader('X-Client-Key', $f['client'])->getJson('/api/partner/v1/outlets')->assertUnauthorized();
    $new = $this->actingAs($f['owner'])->postJson('/settings/partner-keys/'.$f['key']->id.'/client-key')->assertCreated()->json('client_key');
    $this->withHeader('X-Client-Key', $new)->getJson('/api/partner/v1/outlets')->assertOk();
});

test('another merchant and cashier cannot rotate partner client keys', function () {
    $f = partnerClientCredentials();
    $this->actingAs(User::factory()->create(['role' => 'owner']))->postJson('/settings/partner-keys/'.$f['key']->id.'/client-key')->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $f['owner']->id]))->postJson('/settings/partner-keys/'.$f['key']->id.'/client-key')->assertForbidden();
    expect($f['key']->fresh()->client_key_hash)->toBe(hash('sha256', $f['client']));
});

test('tenant admin may issue pairs but expired or revoked credentials cannot rotate client keys', function () {
    $f = partnerClientCredentials();
    $admin = User::factory()->create(['role' => 'admin', 'tenant_owner_id' => $f['owner']->id]);
    $this->actingAs($admin)->postJson('/settings/partner-keys', ['name' => 'Admin integration', 'sandbox' => false, 'expires_in_days' => 30])->assertCreated()->assertJsonPath('data.active', true);
    $this->postJson('/settings/partner-keys/'.$f['key']->id.'/client-key')->assertCreated();
    $f['key']->update(['expires_at' => now()->subMinute()]);
    $this->postJson('/settings/partner-keys/'.$f['key']->id.'/client-key')->assertUnprocessable();
    $this->getJson('/settings/partner-keys')->assertOk()->assertJsonMissingPath('data.0.client_key_hash');
});

test('operator command issues both credentials without persisting plaintext', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    Artisan::call('partner:key', ['email' => $owner->email, '--sandbox' => true]);
    $output = Artisan::output();
    preg_match('/Bearer token: (\S+)/', $output, $bearer);
    preg_match('/X-Client-Key: (\S+)/', $output, $client);
    expect(isset($bearer[1],$client[1]))->toBeTrue();
    $this->withToken($bearer[1])->withHeader('X-Client-Key', $client[1])->getJson('/api/partner/sandbox/v1/outlets')->assertOk();
});
