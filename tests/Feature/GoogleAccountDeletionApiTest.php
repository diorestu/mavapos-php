<?php

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function googleDeletionJwt(array $overrides = [], bool $wrongSignature = false, bool $mockKeys = true): string
{
    static $privateKey;
    if (! $privateKey) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
    }
    $details = openssl_pkey_get_details(openssl_pkey_get_private($privateKey));
    $b64 = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    if ($mockKeys) {
        Http::fake(['https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [[
            'kid' => 'qa-google-rsa', 'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig',
            'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e']),
        ]]], 200, ['Cache-Control' => 'public, max-age=3600'])]);
    }
    config(['services.google.client_id' => 'mava-web-client']);
    $payload = array_replace(['sub' => 'google-self', 'aud' => 'mava-web-client', 'iss' => 'https://accounts.google.com', 'iat' => time(), 'exp' => time() + 3600], $overrides);
    if ($wrongSignature) {
        $wrongKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($wrongKey, $signingKey);
    } else {
        $signingKey = $privateKey;
    }

    return JWT::encode($payload, $signingKey, 'RS256', 'qa-google-rsa');
}

test('API deletes google linked account only after validating a fresh signed ID token', function () {
    $jwt = googleDeletionJwt();
    $user = User::factory()->create(['google_id' => 'google-self']);
    $token = $user->createToken('mobile-pos')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/account/delete', [
        'verification' => 'google', 'google_id_token' => $jwt, 'confirmation' => 'HAPUS AKUN', 'understand' => true,
    ])->assertOk()->assertJsonPath('deleted', true);
    $this->assertSoftDeleted('users', ['id' => $user->id]);
    expect($user->tokens()->count())->toBe(0);
    Http::assertSentCount(1);
});

test('API refuses untrusted expired stale or wrong identity google tokens', function (array $overrides, bool $wrongSignature) {
    $jwt = googleDeletionJwt($overrides, $wrongSignature);
    $user = User::factory()->create(['google_id' => 'google-self']);
    $token = $user->createToken('mobile-pos')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/account/delete', [
        'verification' => 'google', 'google_id_token' => $jwt, 'confirmation' => 'HAPUS AKUN', 'understand' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('google_id_token');
    expect($user->fresh()->trashed())->toBeFalse();
    expect($user->tokens()->count())->toBe(1);
})->with([
    [['aud' => 'another-app'], false], [['iss' => 'https://untrusted.example'], false], [['sub' => 'different-user'], false],
    [['exp' => 1], false], [['iat' => 1], false], [[], true], [['exp' => null], false],
]);

test('google key retrieval failure returns JSON service unavailable without deleting an account', function () {
    $jwt = googleDeletionJwt(mockKeys: false);
    Cache::flush();
    Http::fake(['https://www.googleapis.com/oauth2/v3/certs' => Http::response([], 503)]);
    $user = User::factory()->create(['google_id' => 'google-self']);
    $token = $user->createToken('mobile-pos')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/account/delete', [
        'verification' => 'google', 'google_id_token' => $jwt, 'confirmation' => 'HAPUS AKUN', 'understand' => true,
    ])->assertStatus(503)->assertJsonStructure(['message']);
    expect($user->fresh()->trashed())->toBeFalse();
});
