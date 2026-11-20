<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerApiKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class KeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => PartnerApiKey::with('owner')->where('owner_id', $request->user()->tenantOwnerId())->latest('id')->get()->map(fn ($key) => $this->metadata($key))])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'sandbox' => 'required|boolean', 'expires_in_days' => 'required|integer|min:1|max:365']);
        $owner = User::findOrFail($request->user()->tenantOwnerId());
        abort_unless($owner->role === 'owner', 403);
        [$key, $plain, $client] = PartnerApiKey::issue($owner, $data['name'], $data['sandbox'], now()->addDays($data['expires_in_days']));

        return response()->json(['data' => $this->metadata($key), 'token' => $plain, 'client_key' => $client], 201)->header('Cache-Control', 'private, no-store');
    }

    public function rotateClientKey(Request $request, int $key): JsonResponse
    {
        return DB::transaction(function () use ($request, $key): JsonResponse {
            $credential = PartnerApiKey::with('owner')->where('owner_id', $request->user()->tenantOwnerId())->lockForUpdate()->findOrFail($key);
            abort_unless($credential->usable(), 422, 'Token sudah kedaluwarsa atau dicabut. Buat pasangan kredensial baru.');
            $plain = $credential->rotateClientKey();

            return response()->json(['data' => $this->metadata($credential), 'client_key' => $plain], 201)->header('Cache-Control', 'private, no-store');
        });
    }

    private function metadata(PartnerApiKey $key): array
    {
        return $key->only(['id', 'name', 'prefix', 'sandbox', 'expires_at', 'revoked_at', 'client_key_prefix'])
            + ['client_key_configured' => $key->client_key_hash !== null, 'active' => $key->usable()];
    }

    public function destroy(Request $request, int $key): Response
    {
        PartnerApiKey::where('owner_id', $request->user()->tenantOwnerId())->findOrFail($key)->update(['revoked_at' => now()]);

        return response()->noContent();
    }
}
