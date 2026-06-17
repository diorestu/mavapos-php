<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\Customer;
use App\Services\PakasirClient;
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
    public function __construct(private readonly PakasirClient $pakasir)
    {
    }

    public function index(): View
    {
        return view('pages.billing.index', [
            'title' => 'Billing',
            'pakasirConfigured' => $this->pakasir->isConfigured(),
            'billings' => Billing::query()
                ->with('customer')
                ->latest()
                ->get()
                ->map(fn (Billing $billing): array => $this->payload($billing))
                ->values(),
            'customers' => Customer::query()
                ->where('status', 'aktif')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'phone'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'code' => $customer->code,
                    'phone' => $customer->phone ?? '',
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'integer', 'min:1000'],
        ]);

        if (! $this->pakasir->isConfigured()) {
            return back()
                ->withInput()
                ->withErrors(['pakasir' => 'Konfigurasi Pakasir belum lengkap. Isi PAKASIR_PROJECT dan PAKASIR_API_KEY di .env.']);
        }

        $customer = isset($validated['customer_id'])
            ? Customer::query()->find($validated['customer_id'])
            : null;

        try {
            DB::transaction(function () use ($validated, $customer): void {
                $billing = Billing::query()->create([
                    'invoice_number' => $this->nextInvoiceNumber(),
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name ?? $validated['customer_name'],
                    'customer_phone' => $customer?->phone ?? ($validated['customer_phone'] ?? null),
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'amount' => (int) $validated['amount'],
                    'payment_status' => 'pending',
                ]);

                $response = $this->pakasir->createQrisPayment($billing->invoice_number, $billing->amount);
                $payment = $response['payment'] ?? $response['data'] ?? $response;

                $billing->update([
                    'fee' => Arr::get($payment, 'fee'),
                    'total_payment' => Arr::get($payment, 'total_payment'),
                    'payment_number' => Arr::get($payment, 'payment_number'),
                    'expires_at' => $this->parseDate(Arr::get($payment, 'expired_at')),
                    'payment_url' => $this->paymentUrl($billing),
                    'provider_payload' => ['create' => $response],
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['pakasir' => 'Gagal membuat pembayaran QRIS Pakasir: '.$exception->getMessage()]);
        }

        return redirect()->route('billings')->with('success', 'Tagihan QRIS berhasil dibuat.');
    }

    public function refresh(Billing $billing): RedirectResponse
    {
        try {
            $response = $this->pakasir->detailPayment($billing->invoice_number, $billing->amount);
            $transaction = $response['transaction'] ?? $response['data'] ?? $response;

            $billing->update([
                'payment_status' => Arr::get($transaction, 'status', $billing->payment_status),
                'fee' => Arr::get($transaction, 'fee', $billing->fee),
                'total_payment' => Arr::get($transaction, 'total_payment', $billing->total_payment),
                'payment_number' => Arr::get($transaction, 'payment_number', $billing->payment_number),
                'expires_at' => $this->parseDate(Arr::get($transaction, 'expired_at')) ?? $billing->expires_at,
                'paid_at' => Arr::get($transaction, 'completed_at') ? $this->parseDate(Arr::get($transaction, 'completed_at')) : $billing->paid_at,
                'provider_payload' => [
                    ...($billing->provider_payload ?? []),
                    'detail' => $response,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['pakasir' => 'Gagal cek status Pakasir: '.$exception->getMessage()]);
        }

        return back()->with('success', "Status {$billing->invoice_number} diperbarui.");
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $billing = Billing::query()->firstWhere('invoice_number', $payload['order_id'] ?? null);

        if (! $billing) {
            return response()->json(['message' => 'Billing not found.'], 404);
        }

        if (($payload['project'] ?? null) !== $this->pakasir->project()) {
            return response()->json(['message' => 'Invalid project.'], 422);
        }

        if ((int) ($payload['amount'] ?? 0) !== $billing->amount) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        try {
            $detail = $this->pakasir->detailPayment($billing->invoice_number, $billing->amount);
            $transaction = $detail['transaction'] ?? $detail['data'] ?? $detail;
            $status = (string) ($transaction['status'] ?? $payload['status'] ?? 'pending');

            $billing->update([
                'payment_status' => $status,
                'fee' => Arr::get($transaction, 'fee', $billing->fee),
                'total_payment' => Arr::get($transaction, 'total_payment', $billing->total_payment),
                'payment_number' => Arr::get($transaction, 'payment_number', $billing->payment_number),
                'provider_payload' => [
                    ...($billing->provider_payload ?? []),
                    'webhook' => $payload,
                    'verified_detail' => $detail,
                ],
            ]);

            if ($status === 'completed') {
                $billing->markPaid($payload['completed_at'] ?? Arr::get($transaction, 'completed_at'), $payload);
            }
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Webhook verification failed.'], 422);
        }

        return response()->json(['status' => 'ok']);
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
            'amount' => $billing->amount,
            'amountFormatted' => $this->formatRupiah($billing->amount),
            'totalPaymentFormatted' => $this->formatRupiah($billing->total_payment ?? $billing->amount),
            'paymentStatus' => $billing->payment_status,
            'paymentStatusLabel' => $this->statusLabel($billing->payment_status),
            'paymentUrl' => $billing->payment_url ?? $this->paymentUrl($billing),
            'paymentNumber' => $billing->payment_number ?? '',
            'createdAt' => $billing->created_at?->format('d M Y H:i') ?? '',
            'paidAt' => $billing->paid_at?->format('d M Y H:i') ?? '',
            'expiresAt' => $billing->expires_at?->format('d M Y H:i') ?? '',
            'refreshUrl' => route('billings.refresh', $billing),
        ];
    }

    private function nextInvoiceNumber(): string
    {
        return 'INV-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }

    private function paymentUrl(Billing $billing): string
    {
        return 'https://app.pakasir.com/pay/'.config('services.pakasir.project').'/'.$billing->amount.'?order_id='.$billing->invoice_number.'&qris_only=1';
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
