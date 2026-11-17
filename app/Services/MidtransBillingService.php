<?php

namespace App\Services;

use App\Models\Billing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MidtransBillingService
{
    public function __construct(private readonly MidtransClient $client) {}

    public function validateAmount(Billing $billing, array $data): void
    {
        $amount = $data['gross_amount'] ?? null;
        if (! is_scalar($amount) || ! preg_match('/^\d{1,15}(?:\.0{1,2})?$/D', (string) $amount)
            || (int) $amount !== (int) $billing->amount || ($data['order_id'] ?? null) !== $billing->invoice_number
            || (isset($data['currency']) && $data['currency'] !== 'IDR')) {
            throw ValidationException::withMessages(['payment' => 'Nomor tagihan, mata uang atau nominal pembayaran tidak sesuai.']);
        }
    }

    public function sync(Billing $billing): Billing
    {
        abort_unless($this->client->isConfigured(), 503, 'Midtrans is not configured for this environment.');
        abort_unless(($billing->provider_payload['midtrans_environment'] ?? $this->client->environment()) === $this->client->environment(), 503, 'Payment environment mismatch.');
        $data = $this->client->status($billing->invoice_number);
        if ($data === null) {
            return DB::transaction(function () use ($billing): Billing {
                $locked = Billing::withoutGlobalScopes()->where('payment_provider', 'midtrans')->lockForUpdate()->findOrFail($billing->id);
                if ($locked->paid_at === null && in_array($locked->payment_status, ['pending', 'failed'], true) && $locked->expires_at?->isPast()) {
                    $locked->update(['payment_status' => 'expired']);
                }

                return $locked;
            });
        }
        $this->validateAmount($billing, $data);

        return DB::transaction(function () use ($billing, $data): Billing {
            $locked = Billing::withoutGlobalScopes()->where('payment_provider', 'midtrans')->lockForUpdate()->findOrFail($billing->id);
            if ($locked->payment_number && isset($data['transaction_id']) && $locked->payment_number !== $data['transaction_id']) {
                throw ValidationException::withMessages(['payment' => 'Identitas transaksi pembayaran tidak sesuai.']);
            }
            $raw = (string) ($data['transaction_status'] ?? '');
            $fraud = (string) ($data['fraud_status'] ?? '');
            $success = (string) ($data['status_code'] ?? '') === '200'
                && (($raw === 'settlement' && in_array($fraud, ['', 'accept'], true)) || ($raw === 'capture' && $fraud === 'accept'));
            $status = match (true) {
                in_array($locked->payment_status, ['refunded', 'chargeback'], true) => $locked->payment_status,
                $raw === 'refund' => 'refunded',
                in_array($raw, ['chargeback', 'partial_chargeback'], true) => 'chargeback',
                $success => 'completed',
                $locked->paid_at !== null => $locked->payment_status,
                in_array($raw, ['cancel', 'deny', 'failure'], true) => 'canceled',
                $raw === 'expire' => 'expired',
                default => 'pending',
            };
            $updates = [
                'payment_status' => $status,
                'payment_method' => $data['payment_type'] ?? $locked->payment_method,
                'payment_number' => $data['transaction_id'] ?? $locked->payment_number,
                'provider_payload' => [...($locked->provider_payload ?? []), 'status' => $data],
            ];
            if ($status === 'completed' && $locked->paid_at === null) {
                $paidAt = $data['settlement_time'] ?? $data['transaction_time'] ?? null;
                $updates['paid_at'] = $paidAt ? Carbon::parse($paidAt, 'Asia/Jakarta')->utc() : now();
            }
            $locked->update($updates);

            return $locked->refresh();
        });
    }
}
