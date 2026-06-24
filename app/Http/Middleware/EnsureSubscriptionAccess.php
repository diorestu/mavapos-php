<?php

namespace App\Http\Middleware;

use App\Models\Billing;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isTrialActive() || $this->hasActiveSubscription()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Masa trial atau langganan sudah berakhir. Perpanjang langganan untuk melanjutkan.',
            ], 402);
        }

        return redirect()
            ->route('billings')
            ->withErrors(['subscription' => 'Masa trial atau langganan sudah berakhir. Buat tagihan langganan untuk melanjutkan.']);
    }

    private function hasActiveSubscription(): bool
    {
        return Billing::query()
            ->whereIn('payment_status', ['completed', 'paid'])
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->get()
            ->contains(function (Billing $billing): bool {
                if (! Arr::has($billing->provider_payload ?? [], 'subscription.plan_slug')) {
                    return false;
                }

                $periodEndsAt = Arr::get($billing->provider_payload, 'subscription.period_ends_at');

                return $periodEndsAt
                    ? Carbon::parse($periodEndsAt)->endOfDay()->isFuture()
                    : true;
            });
    }
}
