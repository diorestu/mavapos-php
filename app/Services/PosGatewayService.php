<?php

namespace App\Services;

use App\Http\Controllers\PosController;
use App\Models\GatewayPayment;
use App\Models\PosSale;
use App\Models\SaleRefund;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosGatewayService
{
    public function __construct(private MidtransClient $client, private WalletService $wallet) {}

    public function create(Request $request, PosController $pos): array
    {
        abort_unless($this->client->isConfigured(), 503, 'Gateway belum tersedia.');
        $request->validate(['client_transaction_id' => 'required|uuid']);
        abort_if($request->filled('client_created_at') || $request->filled('online_merchant') || $request->filled('payments') || $request->filled('loyalty_reward') || $request->boolean('loyalty_stamp') || ($request->input('sales_channel', 'direct') !== 'direct'), 422, 'Gateway hanya untuk checkout online langsung, tanpa split atau kartu loyalitas.');
        $owner = $request->user()->tenantOwnerId();
        $branch = app(BranchContext::class)->activeId();
        $payload = $request->only(['items', 'discount', 'customer_name', 'customer_phone', 'buyer_nationality', 'sales_channel', 'expected_total']);
        $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));
        $key = strtolower($request->input('client_transaction_id'));
        [$payment,$created] = DB::transaction(function () use ($request, $pos, $owner, $branch, $hash, $key) {
            DB::table('users')->where('id', $owner)->update(['updated_at' => DB::raw('updated_at')]);
            $merchantOwner = User::query()->whereKey($owner)->lockForUpdate()->firstOrFail();
            abort_unless($merchantOwner->role === 'owner', 422, 'Merchant harus memiliki akun owner sebelum menerima pembayaran gateway.');
            $old = GatewayPayment::where('owner_id', $owner)->where('client_request_id', $key)->first();
            if ($old) {
                abort_unless($old->request_hash === $hash && $old->branch_id === $branch && $old->actor_id === $request->user()->id, 409, 'ID checkout sudah digunakan dengan data berbeda.');

                return [$old, false];
            }
            $request->attributes->set('pos_gateway_checkout', true);
            $request->merge(['payment_method' => 'midtrans', 'sales_channel' => 'direct', 'online_merchant' => null, 'paid_amount' => 0, 'loyalty_stamp' => false, 'loyalty_reward' => null]);
            $response = $pos->checkout($request);
            $id = $response->getData(true)['sale']['id'];
            $sale = PosSale::findOrFail($id);
            abort_if($sale->total <= 0, 422, 'Gateway memerlukan nominal pembayaran lebih dari nol.');
            $p = GatewayPayment::create(['owner_id' => $owner, 'branch_id' => $branch, 'actor_id' => $request->user()->id, 'pos_sale_id' => $sale->id, 'environment' => $this->client->environment(), 'order_id' => 'POS-PG-'.Str::uuid(), 'client_request_id' => $key, 'request_hash' => $hash, 'amount' => $sale->total, 'status' => 'pending', 'creation_status' => 'creating', 'expires_at' => now()->addMinutes(15)]);

            return [$p, true];
        }, 3);
        if (! $created) {
            abort_if($payment->creation_status === 'creating' && $payment->status === 'pending' && $payment->expires_at->isFuture(), 409, 'Sesi pembayaran sedang disiapkan. Ulangi ID checkout yang sama.');

            return [$payment, false];
        }
        try {
            $result = $this->client->createPayment($payment->order_id, $payment->amount, 'Penjualan '.$payment->sale->invoice_number, [], route('pos.gateway.show', $payment), 15);
            DB::transaction(function () use ($payment, $result) {
                $p = GatewayPayment::lockForUpdate()->findOrFail($payment->id);
                $p->update(['snap_token' => $result['token'], 'payment_url' => $result['redirect_url'], 'creation_status' => 'ready', 'provider_payload' => [...($p->provider_payload ?? []), 'create' => $result]]);
            });
        } catch (\Throwable $e) {
            GatewayPayment::whereKey($payment->id)->update(['creation_status' => 'failed']);
            Log::warning('POS gateway session creation failed.', ['payment_id' => $payment->id, 'exception_type' => $e::class]);
        }

        return [$payment->fresh(), true];
    }

    public function validateAmount(GatewayPayment $p, array $d): void
    {
        $value = $d['gross_amount'] ?? null;
        if (! is_scalar($value) || ! preg_match('/^\d{1,15}(?:\.0{1,2})?$/D', (string) $value) || (int) $value !== $p->amount || ($d['order_id'] ?? null) !== $p->order_id || (isset($d['currency']) && $d['currency'] !== 'IDR')) {
            throw ValidationException::withMessages(['payment' => 'Identitas atau nominal pembayaran tidak sesuai.']);
        }
    }

    public function sync(GatewayPayment $payment): GatewayPayment
    {
        abort_unless($this->client->isConfigured() && $payment->environment === $this->client->environment(), 503, 'Environment gateway tidak sesuai.');
        $data = $this->client->status($payment->order_id);
        if ($data) {
            $this->validateAmount($payment, $data);
        }

        return DB::transaction(function () use ($payment, $data) {
            $p = GatewayPayment::lockForUpdate()->findOrFail($payment->id);
            $sale = PosSale::lockForUpdate()->findOrFail($p->pos_sale_id);
            if ($data === null) {
                if ($p->paid_at === null && $p->expires_at->isPast()) {
                    $this->release($p, $sale, 'expire');
                }$p->update(['last_checked_at' => now()]);

                return $p->fresh();
            }
            abort_if($p->transaction_id && isset($data['transaction_id']) && $p->transaction_id !== $data['transaction_id'], 422, 'Identitas transaksi gateway berubah.');
            $raw = (string) $data['transaction_status'];
            $fraud = (string) ($data['fraud_status'] ?? '');
            $received = in_array($raw, ['settlement', 'capture', 'refund', 'partial_refund', 'chargeback', 'partial_chargeback'], true)
                && (string) $data['status_code'] === '200' && ($raw === 'capture' ? $fraud === 'accept' : in_array($fraud, ['', 'accept'], true));
            if ($p->paid_at !== null && (string) $data['status_code'] === '200' && in_array($raw, ['refund', 'partial_refund', 'chargeback', 'partial_chargeback'], true)) {
                $received = true;
            }
            $postPaymentReversal = $p->paid_at !== null && in_array($raw, ['cancel', 'deny', 'failure'], true);
            $settled = in_array($raw, ['settlement', 'refund', 'partial_refund', 'chargeback', 'partial_chargeback'], true) && $received;
            $updates = ['status' => $received ? $raw : ($p->paid_at ? $p->status : (in_array($raw, ['cancel', 'deny', 'expire', 'failure'], true) ? $raw : 'pending')), 'payment_type' => $data['payment_type'] ?? $p->payment_type, 'transaction_id' => $data['transaction_id'] ?? $p->transaction_id, 'provider_payload' => [...($p->provider_payload ?? []), 'status' => $data], 'last_checked_at' => now()];
            if ($p->refunded_amount >= $p->amount && $p->refunded_amount > 0) {
                $updates['status'] = in_array($p->status, ['refund', 'chargeback', 'reversed'], true) ? $p->status : 'refund';
            } elseif ($p->refunded_amount > 0 && in_array($raw, ['settlement', 'capture'], true)) {
                $updates['status'] = 'partial_refund';
            }
            if ($postPaymentReversal) {
                $updates['status'] = 'reversed';
            }
            if ($p->refunded_amount >= $p->amount && $p->settled_at === null) {
                $settled = false;
                if (in_array($raw, ['settlement', 'capture'], true)) {
                    $updates['review_required'] = true;
                }
            }
            if ($received && $p->paid_at === null) {
                $updates['paid_at'] = isset($data['settlement_time']) ? Carbon::parse($data['settlement_time'], 'Asia/Jakarta')->utc() : now();
            }
            if ($settled && $p->settled_at === null) {
                $updates['settled_at'] = now();
            }
            if ($received && $sale->voided_at) {
                $updates['review_required'] = true;
            }
            $p->update($updates);
            if ($received && ! $sale->voided_at && $sale->payment_status !== 'paid') {
                $sale->update(['payment_status' => 'paid', 'paid_amount' => $sale->total]);
                app(CashierShiftSummaryService::class)->refresh($sale->shift);
            }
            if ($settled) {
                $this->wallet->settle($p);
            }
            if ($postPaymentReversal || ($settled && in_array($raw, ['refund', 'partial_refund', 'chargeback', 'partial_chargeback'], true))) {
                $amount = $postPaymentReversal || in_array($raw, ['refund', 'chargeback'], true) ? $p->amount : $this->refundAmount($data);
                if ($amount === null) {
                    $p->update(['review_required' => true]);

                    return $p->fresh();
                }
                $before = $p->refunded_amount;
                if ($p->settled_at !== null) {
                    $this->wallet->refund($p, $amount);
                } elseif ($amount > $p->refunded_amount) {
                    $p->update(['refunded_amount' => $amount]);
                }
                if ($amount > $before) {
                    $delta = $amount - $before;
                    SaleRefund::create(['tenant_id' => $p->owner_id, 'branch_id' => $p->branch_id, 'pos_sale_id' => $sale->id, 'user_id' => $p->actor_id, 'client_refund_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', $p->order_id.':'.$amount), 'refund_number' => 'GWR-'.$p->id.'-'.$amount, 'amount' => $delta, 'refund_method' => 'midtrans', 'reason' => 'Pengembalian dana terverifikasi gateway', 'refunded_at' => now()]);
                }
                if ($amount === $p->amount || (! $sale->voided_at && $amount >= $before)) {
                    $p->update(['review_required' => false]);
                }
            }
            if (! $received && $p->paid_at === null && in_array($raw, ['cancel', 'deny', 'expire', 'failure'], true)) {
                $this->release($p, $sale, $raw);
            }

            return $p->fresh();
        }, 3);
    }

    private function refundAmount(array $data): ?int
    {
        $value = $data['refund_amount'] ?? $data['chargeback_amount'] ?? null;

        return is_scalar($value) && preg_match('/^\d{1,15}(?:\.0{1,2})?$/D', (string) $value) ? (int) $value : null;
    }

    private function release(GatewayPayment $p, PosSale $sale, string $status): void
    {
        if ($sale->payment_status === 'pending' && ! $sale->voided_at) {
            $actor = User::withTrashed()->findOrFail($p->actor_id);
            $guard = auth()->guard();
            $previous = $guard->user();
            $guard->setUser(User::withTrashed()->findOrFail($p->owner_id));
            try {
                app(TransactionVoidService::class)->void($sale, $p->branch_id, $actor, 'Pembayaran gateway '.$status, true);
            } finally {
                if ($previous) {
                    $guard->setUser($previous);
                } else {
                    $guard->forgetUser();
                }
            }
            $sale->update(['payment_status' => 'canceled']);
        }
        $p->update(['status' => $status, 'creation_status' => $p->creation_status === 'creating' ? 'failed' : $p->creation_status]);
    }

    private function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->canonical($value);
            }
        }

        return $data;
    }
}
