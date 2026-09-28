<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SuperAdminMembershipController extends Controller
{
    public function index(): View
    {
        $owners = User::withoutGlobalScopes()->where('role', 'owner')->orderBy('created_at')->get();
        $billings = Billing::withoutGlobalScopes()->whereIn('user_id', $owners->pluck('id'))->get();
        $members = $owners->map(function (User $owner) use ($billings): array {
            $memberBillings = $billings->where('user_id', $owner->id)->sortByDesc('paid_at');
            $latest = $memberBillings->first();
            $subscription = $latest ? Arr::get($latest->provider_payload, 'subscription', []) : [];
            $endsAt = Arr::get($subscription, 'period_ends_at');
            $active = $endsAt ? Carbon::parse($endsAt)->endOfDay()->isFuture() : false;

            $override = $owner->subscription_override_until?->isFuture();
            return ['owner' => $owner, 'plan' => Arr::get($subscription, 'plan_name', '-'), 'status' => $override ? 'Bypass' : ($active ? 'Aktif' : ($owner->isTrialActive() ? 'Trial' : 'Expired')), 'period_ends_at' => $endsAt, 'override_until' => $owner->subscription_override_until, 'revenue' => (int) $memberBillings->whereIn('payment_status', ['paid', 'completed'])->sum('amount')];
        });

        return view('pages.superadmin.memberships', ['title' => 'Membership SaaS', 'members' => $members, 'summary' => ['members' => $members->count(), 'active' => $members->where('status', 'Aktif')->count(), 'trial' => $members->where('status', 'Trial')->count(), 'revenue' => $members->sum('revenue')]]);
    }

    public function extend(Request $request, int $user): RedirectResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($user);
        abort_unless($user->role === 'owner', 404);
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:3650'], 'reason' => ['required', 'string', 'max:500']]);
        $base = $user->subscription_override_until?->isFuture() ? $user->subscription_override_until : now();
        $user->update(['subscription_override_until' => $base->addDays($data['days']), 'subscription_override_reason' => $data['reason'], 'subscription_override_by' => $request->user()->id]);

        return back()->with('success', 'Akses subscription '.$user->email.' diperpanjang.');
    }

    public function bypass(Request $request, int $user): RedirectResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($user);
        abort_unless($user->role === 'owner', 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $user->update(['subscription_override_until' => now()->addYears(100), 'subscription_override_reason' => $data['reason'], 'subscription_override_by' => $request->user()->id]);

        return back()->with('success', 'Bypass subscription '.$user->email.' diaktifkan.');
    }

    public function revoke(Request $request, int $user): RedirectResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($user);
        abort_unless($user->role === 'owner', 404);
        $user->update(['subscription_override_until' => null, 'subscription_override_reason' => 'Bypass dicabut oleh superadmin.', 'subscription_override_by' => $request->user()->id]);

        return back()->with('success', 'Override subscription '.$user->email.' dicabut.');
    }
}
