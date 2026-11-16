<?php

namespace App\Services\Partner;

use Illuminate\Support\Str;

class WebhookMessage
{
    public static function payload(int $id, int $outletId, string $number, ?string $soldAt, bool $sandbox = false): array
    {
        return ['id' => (string) Str::uuid(), 'event' => 'transaction.created', 'occurred_at' => ApiResource::time(now()), 'sandbox' => $sandbox,
            'data' => ['transaction_id' => $id, 'outlet_id' => $outletId, 'transaction_number' => $number, 'sold_at' => $soldAt]];
    }

    public static function encode(array $payload, string $secret): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;

        return ['body' => $body, 'headers' => ['X-Mava-Event-Id' => $payload['id'], 'X-Mava-Timestamp' => $timestamp, 'X-Mava-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret)]];
    }
}
