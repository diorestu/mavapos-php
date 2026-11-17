<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\GatewayPayment;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WalletWithdrawal;
use App\Services\MidtransClient;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function index(Request $request, WalletService $service, MidtransClient $client)
    {
        $d = $request->validate(['environment' => 'sometimes|in:sandbox,production', 'branch_id' => 'nullable|integer|min:1', 'date_from' => 'nullable|date', 'date_to' => 'nullable|date|after_or_equal:date_from']);
        $owner = $request->user()->tenantOwnerId();
        $env = $d['environment'] ?? $client->environment();
        $account = $service->account($owner, $env);
        $branches = Branch::withoutGlobalScope('tenant')->where(fn ($q) => $q->where('user_id', $owner)->when(User::where('role', 'owner')->count() === 1, fn ($q) => $q->orWhereNull('user_id')))->orderBy('name')->get();
        if (! empty($d['branch_id'])) {
            abort_unless($branches->contains('id', (int) $d['branch_id']), 404);
        }
        $from = Carbon::parse($d['date_from'] ?? now('Asia/Makassar')->startOfMonth()->toDateString(), 'Asia/Makassar')->startOfDay();
        $to = Carbon::parse($d['date_to'] ?? now('Asia/Makassar')->toDateString(), 'Asia/Makassar')->endOfDay();
        $payments = GatewayPayment::with(['sale', 'branch'])->where('owner_id', $owner)->where('environment', $env)->when($d['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id));
        $pending = (int) GatewayPayment::where('owner_id', $owner)->where('environment', $env)->whereNull('settled_at')->whereIn('status', ['pending', 'capture'])->sum('amount');
        $entries = WalletEntry::with(['payment.sale', 'withdrawal'])->where('wallet_account_id', $account->id)->when($d['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])->latest('id')->paginate(20, ['*'], 'entries_page')->withQueryString();

        return view('pages.wallet.index', ['title' => 'Wallet', 'account' => $account, 'balances' => $service->balances($account), 'pending' => $pending, 'environment' => $env, 'branches' => $branches, 'filters' => ['branch_id' => $d['branch_id'] ?? '', 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()], 'entries' => $entries, 'payments' => $payments->latest('id')->paginate(10, ['*'], 'payments_page')->withQueryString(), 'withdrawals' => WalletWithdrawal::where('wallet_account_id', $account->id)->latest('id')->paginate(10, ['*'], 'withdrawals_page')->withQueryString(), 'requestId' => (string) Str::uuid()]);
    }

    public function withdraw(Request $request, WalletService $service)
    {
        $d = $request->validate(['environment' => 'required|in:sandbox,production']);
        $service->requestWithdrawal($service->account($request->user()->id, $d['environment']), $request->user(), $request->all());

        return redirect()->route('wallet', ['environment' => $d['environment']])->with('success', 'Pengajuan pencairan tercatat. Dana ditahan sampai diproses admin.');
    }

    public function admin(Request $request, MidtransClient $client, WalletService $service)
    {
        $d = $request->validate(['environment' => 'sometimes|in:sandbox,production', 'owner_id' => 'nullable|integer|exists:users,id']);
        $env = $d['environment'] ?? $client->environment();
        $payments = GatewayPayment::with(['owner', 'branch', 'sale'])->where('environment', $env)->when($d['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id));
        $withdrawals = WalletWithdrawal::with('account.owner')->whereHas('account', fn ($q) => $q->where('environment', $env)->when($d['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id)));

        $withdrawalPage = $withdrawals->latest('id')->paginate(15, ['*'], 'withdrawals_page')->withQueryString();
        $withdrawalPage->getCollection()->each(fn ($withdrawal) => $withdrawal->setAttribute('available_balance', $service->balances($withdrawal->account)['available']));

        return view('pages.wallet.admin', ['title' => 'Administrasi Wallet', 'environment' => $env, 'ownerId' => $d['owner_id'] ?? '', 'owners' => User::withTrashed()->where('role', 'owner')->orderBy('name')->get(['id', 'name']), 'payments' => $payments->latest('id')->paginate(15, ['*'], 'payments_page')->withQueryString(), 'withdrawals' => $withdrawalPage]);
    }

    public function reconcile(Request $request, GatewayPayment $payment, WalletService $service)
    {
        $d = $request->validate(['fee_amount' => 'required|integer|min:0', 'reference' => 'required|string|max:200', 'funds_available' => 'required|accepted']);
        $service->reconcileFee($payment, (int) $d['fee_amount'], $d['reference'], $request->user());

        return back()->with('success', 'Dana bersih merchant telah direkonsiliasi.');
    }

    public function process(Request $request, WalletWithdrawal $withdrawal, WalletService $service)
    {
        $d = $request->validate(['action' => 'required|in:processing,completed,rejected', 'reference' => 'required_if:action,completed,rejected|nullable|string|max:200', 'transferred_confirmation' => 'exclude_unless:action,completed|required|accepted', 'not_transferred' => 'exclude_unless:action,rejected|required|accepted']);
        $service->processWithdrawal($withdrawal, $request->user(), $d['action'], $d['reference'] ?? '');

        return back()->with('success', 'Status pengajuan pencairan diperbarui.');
    }
}
