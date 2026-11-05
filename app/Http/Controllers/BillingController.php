<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\StoreSetting;
use App\Services\SumopodClient;
use App\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class BillingController extends Controller
{
    private const BILLING_CYCLES = [
        'monthly' => [
            'label' => 'Bulanan',
            'months' => 1,
        ],
        'yearly' => [
            'label' => 'Tahunan',
            'months' => 12,
        ],
    ];

    public function __construct(private readonly SumopodClient $sumopod) {}

    public function index(): View
    {
        $setting = StoreSetting::current();
        $currentSubscription = $this->currentSubscription();
        $currentPlanRank = $currentSubscription['plan_slug']
            ? (config('billing.plans')[$currentSubscription['plan_slug']]['rank'] ?? null)
            : null;

        return view('pages.billing.index', [
            'title' => 'Billing',
            'sumopodConfigured' => $this->sumopod->isConfigured(),
            'pakasirConfigured' => $this->sumopod->isConfigured(),
            'plans' => collect(config('billing.plans'))
                ->map(function (array $plan, string $slug) use ($currentSubscription, $currentPlanRank): array {
                    $yearlyBaseAmount = $plan['monthly_amount'] * self::BILLING_CYCLES['yearly']['months'];
                    $yearlyAmount = $this->planAmount($slug, 'yearly');

                    return [
                        ...$plan,
                        'slug' => $slug,
                        'monthly_amount_formatted' => $this->formatRupiah($plan['monthly_amount']),
                        'yearly_base_amount' => $yearlyBaseAmount,
                        'yearly_base_amount_formatted' => $this->formatRupiah($yearlyBaseAmount),
                        'yearly_amount' => $yearlyAmount,
                        'yearly_amount_formatted' => $this->formatRupiah($yearlyAmount),
                        'change_label' => $this->planChangeLabel($plan['rank'], $currentPlanRank, $currentSubscription['active']),
                    ];
                })
                ->values(),
            'cycles' => collect(self::BILLING_CYCLES)
                ->map(fn (array $cycle, string $slug): array => [
                    ...$cycle,
                    'slug' => $slug,
                ])
                ->values(),
            'account' => [
                'name' => $setting->store_name ?: auth()->user()?->name,
                'owner' => $setting->owner_name ?: auth()->user()?->name,
                'phone' => $setting->whatsapp ?: $setting->phone,
                'email' => $setting->email ?: auth()->user()?->email,
            ],
            'currentSubscription' => $currentSubscription,
            'billings' => Billing::query()
                ->with('customer')
                ->latest()
                ->get()
                ->map(fn (Billing $billing): array => $this->payload($billing))
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_slug' => ['required', Rule::in(array_keys(config('billing.plans')))],
            'billing_cycle' => ['required', Rule::in(array_keys(self::BILLING_CYCLES))],
        ]);

        if (! $this->sumopod->isConfigured()) {
            return back()
                ->withInput()
                ->withErrors(['sumopod' => 'Konfigurasi SumoPod belum lengkap. Isi SUMOPOD_API_KEY di .env.']);
        }

        $setting = StoreSetting::current();
        $plan = config('billing.plans')[$validated['plan_slug']];
        $cycle = self::BILLING_CYCLES[$validated['billing_cycle']];
        $amount = $this->planAmount($validated['plan_slug'], $validated['billing_cycle']);
        $accountName = $setting->store_name ?: $request->user()->name;
        $accountPhone = $setting->whatsapp ?: $setting->phone;

        try {
            DB::transaction(function () use ($validated, $plan, $cycle, $amount, $accountName, $accountPhone): void {
                $periodStartsAt = now();
                $periodEndsAt = $periodStartsAt->copy()->addMonthsNoOverflow($cycle['months']);

                $billing = Billing::query()->create([
                    'invoice_number' => $this->nextInvoiceNumber(),
                    'customer_id' => null,
                    'customer_name' => $accountName,
                    'customer_phone' => $accountPhone,
                    'title' => $plan['name'].' - '.$cycle['label'],
                    'description' => $plan['description'].' Ditagihkan '.$this->cycleSentence($validated['billing_cycle']).'.',
                    'amount' => $amount,
                    'payment_provider' => 'sumopod',
                    'payment_method' => 'qris',
                    'payment_status' => 'pending',
                    'provider_payload' => [
                        'subscription' => [
                            'plan_slug' => $validated['plan_slug'],
                            'plan_name' => $plan['name'],
                            'billing_cycle' => $validated['billing_cycle'],
                            'billing_cycle_label' => $cycle['label'],
                            'months' => $cycle['months'],
                            'monthly_amount' => $plan['monthly_amount'],
                            'period_starts_at' => $periodStartsAt->toDateString(),
                            'period_ends_at' => $periodEndsAt->toDateString(),
                        ],
                    ],
                ]);

                $successReturnUrl = route('billings', ['payment_status' => 'success']);
                $cancelReturnUrl = route('billings', ['payment_status' => 'cancel']);

                $response = $this->sumopod->createPayment(
                    orderId: $billing->invoice_number,
                    amount: $billing->amount,
                    successReturnUrl: $successReturnUrl,
                    cancelReturnUrl: $cancelReturnUrl,
                    expiresInHours: 24,
                    paymentMethodTypeCode: 'QRIS',
                );
                $payment = $response['data'] ?? $response;

                $billing->update([
                    'fee' => Arr::get($payment, 'fee', 0),
                    'total_payment' => Arr::get($payment, 'amount', $billing->amount),
                    'payment_number' => Arr::get($payment, 'payment_id', Arr::get($payment, 'payment_number')),
                    'expires_at' => $this->parseDate(Arr::get($payment, 'expires_at')),
                    'payment_url' => Arr::get($payment, 'payment_link_url'),
                    'provider_payload' => [
                        ...($billing->provider_payload ?? []),
                        'create' => $response,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['sumopod' => 'Gagal membuat pembayaran QRIS SumoPod: '.$exception->getMessage()]);
        }

        return redirect()->route('billings')->with('success', 'Tagihan langganan QRIS SumoPod berhasil dibuat.');
    }

    public function refresh(Billing $billing): RedirectResponse
    {
        if ($billing->payment_status === 'completed' || $billing->paid_at !== null) {
            return back()->with('success', "Tagihan {$billing->invoice_number} sudah lunas.");
        }

        if ($billing->expires_at && $billing->expires_at->isPast()) {
            $billing->update(['payment_status' => 'expired']);

            return back()->with('info', "Tagihan {$billing->invoice_number} sudah kedaluwarsa.");
        }

        return back()->with('info', "Tagihan {$billing->invoice_number} menunggu pembayaran QRIS. Status otomatis terupdate ketika webhook notifikasi diterima.");
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $data = (array) ($payload['data'] ?? $payload);

        $orderId = $data['order_id'] ?? $payload['order_id'] ?? null;
        $billing = Billing::query()->firstWhere('invoice_number', $orderId);

        if (! $billing) {
            return response()->json(['message' => 'Billing not found.'], 404);
        }

        $configuredToken = config('services.sumopod.webhook_token');
        if (! empty($configuredToken)) {
            $requestToken = $request->header('X-Webhook-Token');
            if (! hash_equals($configuredToken, (string) $requestToken)) {
                return response()->json(['message' => 'Invalid webhook token.'], 401);
            }
        }

        $receivedAmount = (int) ($data['net_amount'] ?? $data['amount'] ?? $payload['amount'] ?? 0);
        if ($receivedAmount > 0 && $receivedAmount !== (int) $billing->amount && $receivedAmount !== (int) $billing->total_payment) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        $eventType = (string) ($payload['event_type'] ?? $payload['event'] ?? '');
        $rawStatus = (string) ($data['status'] ?? $payload['status'] ?? '');

        $isCompleted = $eventType === 'payment.completed'
            || in_array(strtolower($rawStatus), ['completed', 'paid', 'success', 'succeeded', 'settlement'], true)
            || filled($data['paid_at'] ?? null)
            || filled($data['completed_at'] ?? null);

        $isFailed = $eventType === 'payment.failed' || in_array(strtolower($rawStatus), ['failed', 'canceled', 'cancelled'], true);
        $isExpired = $eventType === 'payment.expired' || strtolower($rawStatus) === 'expired';

        $status = match (true) {
            $isCompleted => 'completed',
            $isFailed => 'canceled',
            $isExpired => 'expired',
            default => $billing->payment_status,
        };

        $billing->update([
            'payment_status' => $status,
            'fee' => Arr::get($data, 'fee', $billing->fee),
            'total_payment' => Arr::get($data, 'amount', $billing->total_payment),
            'payment_number' => Arr::get($data, 'payment_id', $billing->payment_number),
            'provider_payload' => [
                ...($billing->provider_payload ?? []),
                'webhook' => $payload,
            ],
        ]);

        if ($isCompleted) {
            $paidAt = $data['paid_at'] ?? $data['completed_at'] ?? $payload['paid_at'] ?? now()->toISOString();
            $billing->markPaid($paidAt, $payload);
        }

        $billing->refresh();

        return response()->json([
            'status' => 'ok',
            'invoice_number' => $billing->invoice_number,
            'payment_status' => $billing->payment_status,
            'paid_at' => $billing->paid_at?->toISOString(),
        ]);
    }

    private function payload(Billing $billing): array
    {
        return [
            'id' => $billing->id,
            'invoiceNumber' => $billing->invoice_number,
            'customerName' => $billing->customer_name,
            'customerPhone' => $billing->customer_phone ?? '',
            'title' => $billing->title,
            'description' => $billing->description ?? '',
            'planName' => Arr::get($billing->provider_payload, 'subscription.plan_name'),
            'billingCycleLabel' => Arr::get($billing->provider_payload, 'subscription.billing_cycle_label'),
            'periodStartsAt' => Arr::get($billing->provider_payload, 'subscription.period_starts_at'),
            'periodEndsAt' => Arr::get($billing->provider_payload, 'subscription.period_ends_at'),
            'yearlyDiscountPercent' => Arr::get($billing->provider_payload, 'subscription.yearly_discount_percent', 0),
            'amount' => $billing->amount,
            'amountFormatted' => $this->formatRupiah($billing->amount),
            'totalPaymentFormatted' => $this->formatRupiah($billing->total_payment ?? $billing->amount),
            'paymentStatus' => $billing->payment_status,
            'paymentStatusLabel' => $this->statusLabel($billing->payment_status),
            'paymentUrl' => $billing->payment_url ?? $this->paymentUrl($billing),
            'paymentNumber' => $billing->payment_number ?? '',
            'createdAt' => LocalTime::format($billing->created_at, 'd M Y H:i', ''),
            'paidAt' => LocalTime::format($billing->paid_at, 'd M Y H:i', ''),
            'expiresAt' => LocalTime::format($billing->expires_at, 'd M Y H:i', ''),
            'refreshUrl' => route('billings.refresh', $billing),
        ];
    }

    private function nextInvoiceNumber(): string
    {
        return 'INV-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }

    private function paymentUrl(Billing $billing): string
    {
        return $billing->payment_url ?? '';
    }

    private function currentSubscription(): array
    {
        $billing = Billing::query()
            ->whereIn('payment_status', ['completed', 'paid'])
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->get()
            ->first(fn (Billing $billing): bool => Arr::has($billing->provider_payload ?? [], 'subscription.plan_slug'));

        if (! $billing) {
            return [
                'active' => false,
                'status_label' => 'Belum aktif',
                'plan_slug' => null,
                'billing_cycle' => null,
                'plan_name' => '-',
                'billing_cycle_label' => '-',
                'period_starts_at' => null,
                'period_ends_at' => null,
                'paid_at' => null,
                'invoice_number' => null,
                'can_create_billing' => true,
                'renewal_window_starts_at' => null,
            ];
        }

        $periodEndsAt = Arr::get($billing->provider_payload, 'subscription.period_ends_at');
        $periodEndsAtDate = $periodEndsAt ? Carbon::parse($periodEndsAt)->endOfDay() : null;
        $active = $periodEndsAtDate ? $periodEndsAtDate->isFuture() : true;
        $renewalWindowStartsAt = $periodEndsAtDate?->copy()->subDays(7)->startOfDay();
        $canCreateBilling = ! $active || ($renewalWindowStartsAt && now()->startOfDay()->greaterThanOrEqualTo($renewalWindowStartsAt));

        return [
            'active' => $active,
            'status_label' => $active ? 'Aktif' : 'Berakhir',
            'plan_slug' => Arr::get($billing->provider_payload, 'subscription.plan_slug'),
            'billing_cycle' => Arr::get($billing->provider_payload, 'subscription.billing_cycle'),
            'plan_name' => Arr::get($billing->provider_payload, 'subscription.plan_name', $billing->title),
            'billing_cycle_label' => Arr::get($billing->provider_payload, 'subscription.billing_cycle_label', '-'),
            'period_starts_at' => Arr::get($billing->provider_payload, 'subscription.period_starts_at'),
            'period_ends_at' => $periodEndsAt,
            'paid_at' => $billing->paid_at?->format('d M Y H:i'),
            'invoice_number' => $billing->invoice_number,
            'can_create_billing' => $canCreateBilling,
            'renewal_window_starts_at' => $renewalWindowStartsAt?->toDateString(),
        ];
    }

    private function planAmount(string $planSlug, string $billingCycle): int
    {
        $plan = config('billing.plans')[$planSlug];

        return $plan[$billingCycle === 'yearly' ? 'yearly_amount' : 'monthly_amount'];
    }

    private function planChangeLabel(int $planRank, ?int $currentPlanRank, bool $subscriptionActive): string
    {
        if (! $subscriptionActive || $currentPlanRank === null) {
            return 'Pilih Paket';
        }

        return match (true) {
            $planRank > $currentPlanRank => 'Upgrade',
            $planRank < $currentPlanRank => 'Downgrade',
            default => 'Plan Saat Ini',
        };
    }

    private function cycleSentence(string $cycle): string
    {
        return [
            'monthly' => 'bulanan',
            'yearly' => 'tahunan',
        ][$cycle] ?? $cycle;
    }

    private function normalizePaymentStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid', 'success', 'succeeded', 'settlement' => 'completed',
            default => strtolower($status),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $transaction
     */
    private function isSuccessfulPayment(string $status, array $payload, array $transaction): bool
    {
        return $status === 'completed'
            || filled($payload['completed_at'] ?? null)
            || filled(Arr::get($transaction, 'completed_at'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $transaction
     */
    private function successfulPaymentDate(array $payload, array $transaction): ?string
    {
        return $payload['completed_at']
            ?? Arr::get($transaction, 'completed_at')
            ?? Arr::get($transaction, 'paid_at')
            ?? $payload['paid_at']
            ?? null;
    }

    private function parseDate(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    private function formatRupiah(int|float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    private function statusLabel(string $status): string
    {
        return [
            'pending' => 'Menunggu',
            'completed' => 'Lunas',
            'paid' => 'Lunas',
            'canceled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
        ][$status] ?? ucfirst($status);
    }
}
