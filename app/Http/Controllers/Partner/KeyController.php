<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerApiKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => PartnerApiKey::where('owner_id', $request->user()->tenantOwnerId())->latest('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'sandbox' => 'required|boolean', 'expires_in_days' => 'required|integer|min:1|max:365']);
        $owner = User::findOrFail($request->user()->tenantOwnerId());
        abort_unless($owner->role === 'owner', 403);
        [$key, $plain] = PartnerApiKey::issue($owner, $data['name'], $data['sandbox'], now()->addDays($data['expires_in_days']));

        return response()->json(['data' => $key, 'token' => $plain], 201)->header('Cache-Control', 'private, no-store');
    }

    public function destroy(Request $request, int $key): Response
    {
        PartnerApiKey::where('owner_id', $request->user()->tenantOwnerId())->findOrFail($key)->update(['revoked_at' => now()]);

        return response()->noContent();
    }
}
