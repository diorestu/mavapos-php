<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class SumopodClient
{
    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $baseUrl = 'https://api-pay.sumopod.com/api/v1',
        private readonly int $timeout = 30,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createPayment(
        string $orderId,
        int $amount,
        ?string $successReturnUrl = null,
        ?string $cancelReturnUrl = null,
        int $expiresInHours = 24,
        string $paymentMethodTypeCode = 'QRIS',
    ): array {
        $payload = array_filter([
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => 'IDR',
            'expires_in_hours' => $expiresInHours,
            'success_return_url' => $successReturnUrl,
            'cancel_return_url' => $cancelReturnUrl,
            'payment_method_type_code' => $paymentMethodTypeCode,
        ], fn ($val): bool => $val !== null);

        return $this->request()
            ->withHeaders([
                'X-Api-Key' => $this->apiKey,
            ])
            ->post("{$this->baseUrl}/payments", $payload)
            ->throw()
            ->json();
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    private function request()
    {
        return Http::acceptJson()->timeout($this->timeout);
    }
}
