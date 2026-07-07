<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PakasirClient
{
    public function __construct(
        private readonly string $project = '',
        private readonly string $apiKey = '',
        private readonly string $baseUrl = 'https://app.pakasir.com/api',
        private readonly int $timeout = 30,
    ) {}

    public function isConfigured(): bool
    {
        return $this->project !== '' && $this->apiKey !== '';
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createQrisPayment(string $orderId, int $amount): array
    {
        return $this->request()
            ->post("{$this->baseUrl}/transactioncreate/qris", [
                'project' => $this->project,
                'order_id' => $orderId,
                'amount' => $amount,
                'api_key' => $this->apiKey,
            ])
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function detailPayment(string $orderId, int $amount): array
    {
        return $this->request()
            ->get("{$this->baseUrl}/transactiondetail", [
                'project' => $this->project,
                'order_id' => $orderId,
                'amount' => $amount,
                'api_key' => $this->apiKey,
            ])
            ->throw()
            ->json();
    }

    public function project(): string
    {
        return $this->project;
    }

    private function request()
    {
        return Http::acceptJson()->timeout($this->timeout);
    }
}
