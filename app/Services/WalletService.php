<?php

namespace App\Services;

use App\Models\GatewayPayment;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletEntry;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function account(int $ownerId, string $environment): WalletAccount
    {
        abort_unless(in_array($environment, ['sandbox', 'production'], true), 422);

        return WalletAccount::firstOrCreate(['owner_id' => $ownerId, 'environment' => $environment]);
    }

    public function balances(WalletAccount $account, ?int $branchId = null): array
    {
        $sums = WalletEntry::where('wallet_account_id', $account->id)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->selectRaw('bucket, SUM(amount) AS total')->groupBy('bucket')->pluck('total', 'bucket');

        return collect(['unreconciled', 'available', 'reserved', 'paid_out', 'fees'])->mapWithKeys(fn ($key) => [$key => (int) ($sums[$key] ?? 0)])->all();
    }

    private function lockAccount(GatewayPayment $payment): WalletAccount
    {
        $account = $this->account($payment->owner_id, $payment->environment);

        return WalletAccount::lockForUpdate()->findOrFail($account->id);
    }

    private function entry(WalletAccount $account, string $key, string $bucket, int $amount, string $kind, ?GatewayPayment $payment = null, ?WalletWithdrawal $withdrawal = null): void
    {
        WalletEntry::firstOrCreate(['wallet_account_id' => $account->id, 'posting_key' => $key], ['gateway_payment_id' => $payment?->id, 'wallet_withdrawal_id' => $withdrawal?->id, 'branch_id' => $payment?->branch_id, 'bucket' => $bucket, 'amount' => $amount, 'kind' => $kind]);
    }

    public function settle(GatewayPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $p = GatewayPayment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($p->settled_at !== null, 422, 'Dana belum settlement.');
            $a = $this->lockAccount($p);
            $this->entry($a, 'payment:'.$p->id.':settlement', 'unreconciled', $p->amount, 'settlement', $p);
        }, 3);
    }

    public function refund(GatewayPayment $payment, int $cumulative): void
    {
        DB::transaction(function () use ($payment, $cumulative) {
            $p = GatewayPayment::lockForUpdate()->findOrFail($payment->id);
            abort_if($cumulative < 0 || $cumulative > $p->amount, 422, 'Nominal refund tidak sesuai.');
            if ($cumulative <= $p->refunded_amount) {
                return;
            }
            $a = $this->lockAccount($p);
            $delta = $cumulative - $p->refunded_amount;
            $this->entry($a, 'payment:'.$p->id.':refund:'.$cumulative, $p->fee_reconciled_at ? 'available' : 'unreconciled', -$delta, 'refund', $p);
            $p->update(['refunded_amount' => $cumulative]);
        }, 3);
    }

    public function reconcileFee(GatewayPayment $payment, int $fee, string $reference, User $actor): void
    {
        abort_unless($actor->role === 'superadmin', 403);
        DB::transaction(function () use ($payment, $fee, $reference, $actor) {
            $p = GatewayPayment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($p->settled_at, 422, 'Dana belum settlement.');
            abort_if($p->review_required, 422, 'Pembayaran memerlukan tinjauan sebelum dana dapat dicairkan.');
            abort_if($fee < 0 || $fee > $p->amount || trim($reference) === '' || mb_strlen($reference) > 200, 422, 'Biaya dan referensi rekonsiliasi wajib valid.');
            if ($p->fee_reconciled_at) {
                abort_unless($p->fee_amount === $fee && $p->fee_reference === $reference, 409, 'Rekonsiliasi sudah dicatat.');

                return;
            }
            $a = $this->lockAccount($p);
            $remaining = $p->amount - $p->refunded_amount;
            $this->entry($a, 'payment:'.$p->id.':reconcile-out', 'unreconciled', -$remaining, 'reconciliation', $p);
            $this->entry($a, 'payment:'.$p->id.':reconcile-net', 'available', $remaining - $fee, 'reconciliation', $p);
            $this->entry($a, 'payment:'.$p->id.':fee', 'fees', $fee, 'provider_fee', $p);
            $p->update(['fee_amount' => $fee, 'fee_reference' => $reference, 'fee_reconciled_at' => now(), 'fee_confirmed_by' => $actor->id]);
        }, 3);
    }

    public function requestWithdrawal(WalletAccount $account, User $actor, array $data): WalletWithdrawal
    {
        abort_unless($actor->role === 'owner' && $account->owner_id === $actor->id, 403);
        $d = Validator::make($data, ['request_id' => 'required|uuid', 'amount' => 'required|integer|min:1', 'bank_name' => 'required|string|max:100', 'account_number' => ['required', 'string', 'regex:/^[0-9]{6,34}$/D'], 'account_holder' => 'required|string|max:150'])->validate();
        $hash = hash('sha256', json_encode([$d['amount'], $d['bank_name'], $d['account_number'], $d['account_holder']], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($account, $d, $hash) {
            $a = WalletAccount::lockForUpdate()->findOrFail($account->id);
            $existing = WalletWithdrawal::where('wallet_account_id', $a->id)->where('request_id', $d['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'ID pengajuan sudah dipakai dengan data berbeda.');

                return $existing;
            }
            abort_if(GatewayPayment::where('owner_id', $a->owner_id)->where('environment', $a->environment)->where('review_required', true)->exists(), 422, 'Wallet memerlukan tinjauan admin sebelum pencairan.');
            if ($this->balances($a)['available'] < (int) $d['amount']) {
                throw ValidationException::withMessages(['amount' => 'Saldo tersedia tidak mencukupi.']);
            }
            $w = WalletWithdrawal::create(['wallet_account_id' => $a->id, 'request_id' => $d['request_id'], 'request_hash' => $hash, 'amount' => $d['amount'], 'bank_name' => $d['bank_name'], 'account_number' => $d['account_number'], 'account_holder' => $d['account_holder'], 'status' => 'pending']);
            $this->entry($a, 'withdrawal:'.$w->id.':hold', 'available', -$w->amount, 'withdrawal_hold', withdrawal: $w);
            $this->entry($a, 'withdrawal:'.$w->id.':reserve', 'reserved', $w->amount, 'withdrawal_hold', withdrawal: $w);

            return $w;
        }, 3);
    }

    public function processWithdrawal(WalletWithdrawal $withdrawal, User $actor, string $action, string $reference = ''): WalletWithdrawal
    {
        abort_unless($actor->role === 'superadmin', 403);
        abort_unless(in_array($action, ['processing', 'completed', 'rejected'], true), 422);

        return DB::transaction(function () use ($withdrawal, $actor, $action, $reference) {
            $w = WalletWithdrawal::lockForUpdate()->findOrFail($withdrawal->id);
            $a = WalletAccount::lockForUpdate()->findOrFail($w->wallet_account_id);
            if ($w->status === $action) {
                abort_if($action === 'completed' && $w->reference !== $reference, 409);

                return $w;
            }
            abort_if(in_array($w->status, ['completed', 'rejected'], true), 409, 'Pengajuan sudah selesai.');
            if ($action === 'processing') {
                abort_if(GatewayPayment::where('owner_id', $a->owner_id)->where('environment', $a->environment)->where('review_required', true)->exists(), 422, 'Wallet memerlukan tinjauan admin.');
                abort_unless($w->status === 'pending', 409);
                abort_if($this->balances($a)['available'] < 0, 422, 'Wallet memiliki kewajiban yang belum tertutup.');
            }
            if (in_array($action, ['completed', 'rejected'], true)) {
                abort_if(trim($reference) === '' || mb_strlen($reference) > 200, 422, 'Referensi transfer atau alasan penolakan wajib diisi.');
                if ($action === 'completed') {
                    abort_unless($w->status === 'processing', 409, 'Proses pengajuan sebelum menandai transfer selesai.');
                }
                $this->entry($a, 'withdrawal:'.$w->id.':release', 'reserved', -$w->amount, 'withdrawal_'.$action, withdrawal: $w);
                $this->entry($a, 'withdrawal:'.$w->id.':final', $action === 'completed' ? 'paid_out' : 'available', $w->amount, 'withdrawal_'.$action, withdrawal: $w);
            }
            $w->update(['status' => $action, 'reference' => $reference ?: null, 'processed_by' => $actor->id, 'processed_at' => now()]);

            return $w->refresh();
        }, 3);
    }
}
