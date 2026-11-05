<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleDeletionTokenVerifier
{
    private const CACHE_KEY = 'account-deletion-google-public-keys';

    public function verify(#[\SensitiveParameter] string $token, User $user): void
    {
        $clientId = config('services.google.client_id');
        abort_unless(is_string($clientId) && $clientId !== '', 503, 'Verifikasi Google belum dikonfigurasi.');
        if (! $user->google_id) {
            $this->invalidToken();
        }
        try {
            $parts = explode('.', $token);
            $header = count($parts) === 3 ? json_decode(JWT::urlsafeB64Decode($parts[0]), true, 32, JSON_THROW_ON_ERROR) : null;
        } catch (\Throwable $error) {
            $this->invalidToken();
        }
        if (! is_array($header) || ($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null) || $header['kid'] === '' || strlen($header['kid']) > 256) {
            $this->invalidToken();
        }
        $jwks = $this->publicKeys();
        if (! collect($jwks['keys'])->contains('kid', $header['kid'])) {
            $jwks = $this->publicKeys(refresh: true);
        }
        try {
            $keys = JWK::parseKeySet($jwks, 'RS256');
        } catch (\Throwable $error) {
            abort(503, 'Kunci verifikasi Google belum tersedia. Coba lagi nanti.');
        }
        try {
            $claims = JWT::decode($token, $keys);
        } catch (\Throwable $error) {
            $this->invalidToken();
        }
        if (! in_array($claims->iss ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ($claims->aud ?? null) !== $clientId || ! is_string($claims->sub ?? null) || ! hash_equals((string) $user->google_id, $claims->sub)
            || ! is_numeric($claims->exp ?? null) || $claims->exp <= now()->timestamp
            || ! is_numeric($claims->iat ?? null) || $claims->iat < now()->subMinutes(5)->timestamp || $claims->iat > now()->timestamp) {
            $this->invalidToken();
        }
    }

    private function publicKeys(bool $refresh = false): array
    {
        $cached = $refresh ? null : Cache::get(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['keys'])) {
            return $cached;
        }
        try {
            $response = Http::timeout(10)->get('https://www.googleapis.com/oauth2/v3/certs');
        } catch (\Throwable $error) {
            abort(503, 'Layanan verifikasi Google belum dapat dihubungi.');
        }
        abort_unless($response->successful() && is_array($response->json('keys')), 503, 'Kunci verifikasi Google belum tersedia.');
        $keys = collect($response->json('keys'))->filter(fn ($key): bool => is_array($key)
            && ($key['kty'] ?? null) === 'RSA' && ($key['alg'] ?? 'RS256') === 'RS256')->values()->all();
        abort_if($keys === [], 503, 'Kunci verifikasi Google belum tersedia.');
        preg_match('/max-age=(\d+)/', $response->header('Cache-Control') ?? '', $matches);
        $ttl = max(1, min((int) ($matches[1] ?? 3600), 86400));
        Cache::put(self::CACHE_KEY, ['keys' => $keys], $ttl);

        return ['keys' => $keys];
    }

    private function invalidToken(): never
    {
        throw ValidationException::withMessages(['google_id_token' => 'Token Google tidak valid atau tidak sesuai akun. Verifikasi ulang Google dan kirim ID token yang baru.']);
    }
}
