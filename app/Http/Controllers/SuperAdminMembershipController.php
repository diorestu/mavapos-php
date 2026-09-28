<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

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

            return ['owner' => $owner, 'plan' => Arr::get($subscription, 'plan_name', '-'), 'status' => $active ? 'Aktif' : ($owner->isTrialActive() ? 'Trial' : 'Expired'), 'period_ends_at' => $endsAt, 'revenue' => (int) $memberBillings->whereIn('payment_status', ['paid', 'completed'])->sum('amount')];
        });

        return view('pages.superadmin.memberships', ['title' => 'Membership SaaS', 'members' => $members, 'summary' => ['members' => $members->count(), 'active' => $members->where('status', 'Aktif')->count(), 'trial' => $members->where('status', 'Trial')->count(), 'revenue' => $members->sum('revenue')]]);
    }
}
