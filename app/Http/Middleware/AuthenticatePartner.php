<?php

namespace App\Http\Middleware;

use App\Models\PartnerApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) <= 200, 401, 'Bearer API key required.');
        $key = PartnerApiKey::with('owner')->where('token_hash', hash('sha256', $token))->first();
        abort_unless($key && $key->usable(), 401, 'API key expired, revoked or invalid.');
        $client = $request->header('X-Client-Key');
        abort_unless(is_string($client) && $client !== '' && strlen($client) <= 200
            && is_string($key->client_key_hash) && hash_equals($key->client_key_hash, hash('sha256', $client)), 401, 'Client Key missing or invalid.');
        abort_unless($key->sandbox === $request->is('api/partner/sandbox/*'), 403, 'API key environment mismatch.');
        $request->attributes->set('partner_key', $key);

        return $next($request)->header('Cache-Control', 'private, no-store');
    }
}
