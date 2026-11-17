<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MidtransClient
{
    public function __construct(
        private readonly string $serverKey = '',
        private readonly bool $isProduction = false,
        private readonly int $timeout = 20,
        private readonly string $clientKey = '',
    ) {}

    public function isConfigured(): bool
    {
        return str_starts_with($this->serverKey, $this->isProduction ? 'Mid-server-' : 'SB-Mid-server-');
    }

    public function environment(): string
    {
        return $this->isProduction ? 'production' : 'sandbox';
    }

    public function snapConfiguration(): array
    {
        $validClientKey = trim($this->clientKey) !== '';

        return [
            'ready' => $this->isConfigured() && $validClientKey,
            'clientKey' => $validClientKey ? $this->clientKey : '',
            'scriptUrl' => $this->isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js',
        ];
    }

    public function createPayment(string $orderId, int $amount, string $title, array $customer, string $finishUrl, int $expiryMinutes = 1440): array
    {
        $url = $this->isProduction ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
        $response = $this->request()->post($url.'/snap/v1/transactions', [
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
            'item_details' => [['id' => 'subscription', 'price' => $amount, 'quantity' => 1, 'name' => mb_substr($title, 0, 50)]],
            'customer_details' => array_filter($customer, fn ($value) => filled($value)),
            'callbacks' => ['finish' => $finishUrl],
            'expiry' => ['start_time' => now('Asia/Jakarta')->format('Y-m-d H:i:s O'), 'unit' => 'minutes', 'duration' => $expiryMinutes],
            'page_expiry' => ['unit' => 'minutes', 'duration' => $expiryMinutes],
        ])->throw()->json();
        $host = $this->isProduction ? 'app.midtrans.com' : 'app.sandbox.midtrans.com';
        if (! is_array($response) || ! is_string($response['token'] ?? null) || $response['token'] === '' || ! is_string($response['redirect_url'] ?? null)
            || parse_url($response['redirect_url'], PHP_URL_SCHEME) !== 'https' || parse_url($response['redirect_url'], PHP_URL_HOST) !== $host) {
            throw new \RuntimeException('Midtrans returned an invalid checkout response.');
        }

        return $response;
    }

    public function status(string $orderId): ?array
    {
        $url = $this->isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
        $response = $this->request()->get($url.'/v2/'.rawurlencode($orderId).'/status');
        if (in_array($response->status(), [200, 404], true) && (string) $response->json('status_code') === '404') {
            return null;
        }

        $data = $response->throw()->json();
        if (! is_array($data) || ! isset($data['order_id'], $data['transaction_status'], $data['gross_amount'], $data['status_code'])) {
            throw new \UnexpectedValueException('Midtrans returned an invalid status response.');
        }

        return $data;
    }

    public function validSignature(array $data): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! isset($data[$field]) || ! is_scalar($data[$field])) {
                return false;
            }
        }
        $expected = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].$this->serverKey);

        return $this->isConfigured() && hash_equals($expected, (string) $data['signature_key']);
    }

    private function request()
    {
        return Http::acceptJson()->asJson()->withBasicAuth($this->serverKey, '')->connectTimeout(5)->timeout($this->timeout);
    }
}
