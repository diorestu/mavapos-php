<?php

namespace App\Console\Commands;

use App\Models\PartnerWebhookDelivery;
use App\Services\Partner\WebhookDestination;
use App\Services\Partner\WebhookMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendPartnerWebhooks extends Command
{
    protected $signature = 'partner:webhooks:send {--limit=100}';

    protected $description = 'Deliver due partner webhooks with bounded retries';

    public function handle(WebhookDestination $destination): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! $limit || $limit < 1 || $limit > 1000) {
            $this->error('Use --limit=1..1000.');

            return self::FAILURE;
        }
        $ids = PartnerWebhookDelivery::whereNull('delivered_at')->whereNull('failed_at')->where('available_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $delivery = DB::transaction(function () use ($id) {
                $row = PartnerWebhookDelivery::lockForUpdate()->find($id);
                if (! $row || $row->delivered_at || $row->failed_at || $row->available_at->isFuture()) {
                    return null;
                }
                if ($row->attempts >= 5) {
                    $row->update(['failed_at' => now(), 'last_error' => 'Maximum attempts reached.']);

                    return null;
                }
                $row->update(['attempts' => $row->attempts + 1, 'available_at' => now()->addMinutes(5)]);

                return $row;
            });
            if (! $delivery) {
                continue;
            }
            $hook = $delivery->webhook;
            if (! $hook || ! $hook->key?->usable() || $hook->key->sandbox) {
                $delivery->update(['failed_at' => now(), 'last_error' => 'Webhook or API key disabled.']);

                continue;
            }
            $status = null;
            try {
                $options = $destination->options($hook->url);
                $message = WebhookMessage::encode($delivery->payload, $hook->signing_secret);
                $response = Http::withOptions($options)->connectTimeout(3)->timeout(10)->withHeaders($message['headers'])
                    ->withBody($message['body'], 'application/json')->post($hook->url);
                $status = $response->status();
                if ($response->successful()) {
                    $delivery->update(['delivered_at' => now(), 'last_status' => $status, 'last_error' => null]);

                    continue;
                }
                $error = 'Receiver returned HTTP '.$status.'.';
            } catch (\Throwable) {
                $error = 'Destination validation or delivery failed.';
            }
            $delay = [60, 300, 900, 3600, 3600][$delivery->attempts - 1];
            $delivery->update(['last_status' => $status, 'last_error' => $error, 'available_at' => now()->addSeconds($delay), 'failed_at' => $delivery->attempts >= 5 ? now() : null]);
        }

        return self::SUCCESS;
    }
}
