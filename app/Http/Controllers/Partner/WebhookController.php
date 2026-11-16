<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerWebhook;
use App\Services\Partner\WebhookDestination;
use App\Services\Partner\WebhookMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['data' => $this->query($request)->first()]);
    }

    public function store(Request $request, WebhookDestination $destination)
    {
        $data = $request->validate(['url' => 'required|string|max:2048']);
        $destination->host($data['url']);

        return DB::transaction(function () use ($request, $data) {
            $key = $request->attributes->get('partner_key')->newQuery()->lockForUpdate()->findOrFail($request->attributes->get('partner_key')->id);
            abort_unless($key->usable(), 401);
            abort_if($this->query($request)->exists(), 409, 'Delete the current webhook before registering another.');
            $secret = 'whsec_'.Str::random(64);
            $hook = PartnerWebhook::create(['partner_api_key_id' => $key->id, 'url' => $data['url'], 'signing_secret' => $secret]);

            return response()->json(['data' => $hook, 'signing_secret' => $secret], 201)->header('Cache-Control', 'private, no-store');
        });
    }

    public function destroy(Request $request)
    {
        $this->query($request)->delete();

        return response()->noContent();
    }

    public function test(Request $request)
    {
        $key = $request->attributes->get('partner_key');
        abort_unless($key->sandbox, 403, 'Synthetic tests are available only in sandbox.');
        $hook = $this->query($request)->firstOrFail();
        $payload = WebhookMessage::payload(900001, 900001, 'SANDBOX-900001', '2026-11-13T10:00:00+08:00', true);

        return response()->json(WebhookMessage::encode($payload, $hook->signing_secret) + ['sent' => false]);
    }

    private function query(Request $request)
    {
        return PartnerWebhook::where('partner_api_key_id', $request->attributes->get('partner_key')->id);
    }
}
