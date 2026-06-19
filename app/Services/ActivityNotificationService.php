<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\CashierShift;
use App\Models\NotificationRead;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;

class ActivityNotificationService
{
    public function allForUser(User $user, int $limit = 50): Collection
    {
        $activities = $this->buildActivities($limit);
        $readKeys = NotificationRead::query()
            ->where('user_id', $user->id)
            ->whereIn('notification_key', $activities->pluck('key'))
            ->pluck('read_at', 'notification_key');

        return $activities
            ->map(fn (array $activity): array => [
                ...$activity,
                'read' => $readKeys->has($activity['key']),
                'read_at' => $readKeys->get($activity['key']),
            ])
            ->values();
    }

    public function unreadCountForUser(User $user): int
    {
        return $this->allForUser($user, 100)
            ->where('read', false)
            ->count();
    }

    public function markAllAsRead(User $user): int
    {
        $now = now();
        $activities = $this->buildActivities(100);
        $existingKeys = NotificationRead::query()
            ->where('user_id', $user->id)
            ->whereIn('notification_key', $activities->pluck('key'))
            ->pluck('notification_key')
            ->all();

        $rows = $activities
            ->reject(fn (array $activity): bool => in_array($activity['key'], $existingKeys, true))
            ->map(fn (array $activity): array => [
                'user_id' => $user->id,
                'notification_key' => $activity['key'],
                'read_at' => $now,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return 0;
        }

        NotificationRead::query()->insert($rows);

        return count($rows);
    }

    public function buildActivities(int $limit = 50): Collection
    {
        $lowStockProducts = Product::query()
            ->where('min_stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit($limit)
            ->get();

        $saleActivities = PosSale::query()
            ->with('user')
            ->latest('sold_at')
            ->limit($limit)
            ->get()
            ->map(fn (PosSale $sale): array => [
                'key' => 'sale:'.$sale->id.':'.optional($sale->updated_at)->timestamp,
                'type' => 'Penjualan',
                'title' => 'Transaksi '.$sale->invoice_number.' selesai',
                'description' => ($sale->user?->name ?? 'Kasir').' menerima '.$this->formatRupiah($sale->total).' via '.$this->paymentLabel($sale->payment_method).'.',
                'time' => $sale->sold_at?->diffForHumans() ?? $sale->created_at?->diffForHumans(),
                'timestamp' => $sale->sold_at ?? $sale->created_at,
                'url' => route('sales', [
                    'search' => $sale->invoice_number,
                    'date_from' => $sale->sold_at?->toDateString(),
                    'date_to' => $sale->sold_at?->toDateString(),
                ]),
                'tone' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
                'icon' => 'Rp',
                'attention' => false,
            ]);

        $shiftActivities = CashierShift::query()
            ->with('user')
            ->latest('opened_at')
            ->limit($limit)
            ->get()
            ->flatMap(function (CashierShift $shift): array {
                $activities = [[
                    'key' => 'shift-opened:'.$shift->id.':'.optional($shift->updated_at)->timestamp,
                    'type' => 'Shift Kasir',
                    'title' => 'Shift '.$this->cashierName($shift).' dimulai',
                    'description' => 'Absensi kasir dicatat dan kasir siap transaksi.',
                    'time' => $shift->opened_at?->diffForHumans(),
                    'timestamp' => $shift->opened_at,
                    'url' => route('cashier-shifts'),
                    'tone' => $shift->closed_at ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300',
                    'icon' => 'KS',
                    'attention' => $shift->closed_at === null,
                ]];

                if ($shift->closed_at) {
                    $activities[] = [
                        'key' => 'shift-closed:'.$shift->id.':'.optional($shift->updated_at)->timestamp,
                        'type' => 'Shift Kasir',
                        'title' => 'Shift '.$this->cashierName($shift).' ditutup',
                        'description' => number_format($shift->sales_count, 0, ',', '.').' transaksi, pendapatan '.$this->formatRupiah($shift->net_sales).'.',
                        'time' => $shift->closed_at->diffForHumans(),
                        'timestamp' => $shift->closed_at,
                        'url' => route('cashier-shifts'),
                        'tone' => 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300',
                        'icon' => 'OK',
                        'attention' => false,
                    ];
                }

                return $activities;
            });

        $stockActivities = StockMovement::query()
            ->with('product')
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'key' => 'stock:'.$movement->id.':'.optional($movement->updated_at)->timestamp,
                'type' => 'Stok',
                'title' => ($movement->type === 'in' ? 'Stok masuk' : 'Stok keluar').' '.$movement->quantity.' item',
                'description' => ($movement->product?->name ?? 'Produk').' sekarang stok '.$movement->stock_after.'.',
                'time' => $movement->occurred_at?->diffForHumans(),
                'timestamp' => $movement->occurred_at,
                'url' => route('inventory'),
                'tone' => $movement->type === 'in'
                    ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300'
                    : 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300',
                'icon' => $movement->type === 'in' ? 'IN' : 'OUT',
                'attention' => false,
            ]);

        $lowStockActivities = $lowStockProducts
            ->map(fn (Product $product): array => [
                'key' => 'low-stock:'.$product->id.':'.$product->stock.':'.$product->min_stock,
                'type' => 'Stok Menipis',
                'title' => $product->name.' perlu perhatian',
                'description' => 'Stok '.$product->stock.' tersisa, minimum '.$product->min_stock.'.',
                'time' => $product->updated_at?->diffForHumans(),
                'timestamp' => $product->updated_at,
                'url' => route('inventory'),
                'tone' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
                'icon' => '!',
                'attention' => true,
            ]);

        $billingActivities = Billing::query()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Billing $billing): array => [
                'key' => 'billing:'.$billing->id.':'.$billing->payment_status.':'.optional($billing->updated_at)->timestamp,
                'type' => 'Billing',
                'title' => 'Tagihan '.$billing->invoice_number.' '.$this->billingStatusLabel($billing->payment_status),
                'description' => $billing->customer_name.' · '.$this->formatRupiah($billing->total_payment ?: $billing->amount),
                'time' => ($billing->paid_at ?? $billing->created_at)?->diffForHumans(),
                'timestamp' => $billing->paid_at ?? $billing->created_at,
                'url' => route('billings'),
                'tone' => $billing->payment_status === 'completed'
                    ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400'
                    : 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300',
                'icon' => $billing->payment_status === 'completed' ? 'OK' : 'QR',
                'attention' => $billing->payment_status !== 'completed',
            ]);

        return collect()
            ->merge($saleActivities)
            ->merge($shiftActivities)
            ->merge($stockActivities)
            ->merge($lowStockActivities)
            ->merge($billingActivities)
            ->filter(fn (array $activity): bool => $activity['timestamp'] !== null)
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values()
            ->map(fn (array $activity): array => [
                ...$activity,
                'time' => $activity['time'] ?: '-',
            ]);
    }

    private function cashierName(CashierShift $shift): string
    {
        return $shift->user?->name ?? 'Kasir';
    }

    private function formatRupiah(int|float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    private function paymentLabel(string $method): string
    {
        return [
            'cash' => 'Tunai',
            'qris' => 'QRIS',
            'card' => 'Kartu',
        ][$method] ?? ucfirst($method);
    }

    private function billingStatusLabel(string $status): string
    {
        return [
            'completed' => 'lunas',
            'pending' => 'menunggu pembayaran',
            'failed' => 'gagal',
            'expired' => 'kedaluwarsa',
        ][$status] ?? $status;
    }
}
