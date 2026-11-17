<?php

namespace App\Console\Commands;

use App\Models\GatewayPayment;
use App\Services\MidtransClient;
use App\Services\PosGatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileGatewayPayments extends Command
{
    protected $signature = 'gateway:reconcile {--limit=20}';

    protected $description = 'Reconcile POS gateway payments and release expired reservations';

    public function handle(MidtransClient $client, PosGatewayService $service): int
    {
        if (! $client->isConfigured()) {
            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! $limit || $limit < 1 || $limit > 200) {
            $this->error('Use --limit=1..200.');

            return self::FAILURE;
        }
        $rows = GatewayPayment::where('environment', $client->environment())->whereIn('status', ['pending', 'capture', 'settlement', 'partial_refund'])->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere(fn ($q) => $q->whereIn('status', ['pending', 'capture'])->where('last_checked_at', '<=', now()->subMinute()))->orWhere('last_checked_at', '<=', now()->subHour()))->orderBy('last_checked_at')->orderBy('id')->limit($limit)->get();
        foreach ($rows as $payment) {
            try {
                $service->sync($payment);
            } catch (\Throwable $e) {
                $payment->update(['last_checked_at' => now()]);
                Log::warning('POS gateway reconciliation failed.', ['payment_id' => $payment->id, 'exception_type' => $e::class]);
            }
        }

        return self::SUCCESS;
    }
}
