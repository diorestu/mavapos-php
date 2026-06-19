<?php

namespace App\Http\Controllers;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now = now();
        $yearStart = $now->copy()->startOfYear();
        $yearEnd = $now->copy()->endOfYear();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $yearSales = PosSale::query()
            ->whereBetween('sold_at', [$yearStart, $yearEnd])
            ->get(['id', 'total', 'sold_at']);
        $monthlySales = $yearSales
            ->groupBy(fn (PosSale $sale): int => $sale->sold_at->month)
            ->map(fn ($sales): array => [
                'count' => $sales->count(),
                'revenue' => $sales->sum('total'),
            ]);
        $monthLabels = collect(range(1, 12))
            ->map(fn (int $month): string => Carbon::create($now->year, $month, 1)->translatedFormat('M'))
            ->values();

        $monthItems = PosSaleItem::query()
            ->with(['product.category', 'productVariant'])
            ->whereHas('sale', fn ($query) => $query->whereBetween('sold_at', [$monthStart, $monthEnd]))
            ->get();
        $grossProfitByMonth = $this->grossProfitByMonth($yearStart, $yearEnd);
        $topProducts = $this->topProducts($monthItems);

        $dashboardData = [
            'metrics' => [
                [
                    'label' => 'Total Produk',
                    'value' => number_format(Product::query()->count(), 0, ',', '.'),
                    'note' => 'Aktif di katalog',
                    'tone' => 'text-brand-500 bg-brand-50 dark:bg-brand-500/15',
                ],
                [
                    'label' => 'Penjualan Hari Ini',
                    'value' => number_format(PosSale::query()->whereBetween('sold_at', [$todayStart, $todayEnd])->count(), 0, ',', '.'),
                    'note' => 'Transaksi selesai',
                    'tone' => 'text-success-600 bg-success-50 dark:bg-success-500/15',
                ],
                [
                    'label' => 'Pendapatan Bulan Ini',
                    'value' => $this->formatCompactRupiah(PosSale::query()->whereBetween('sold_at', [$monthStart, $monthEnd])->sum('total')),
                    'note' => 'Omzet berjalan',
                    'tone' => 'text-warning-700 bg-warning-50 dark:bg-warning-500/15',
                ],
                [
                    'label' => 'Stok Menipis',
                    'value' => number_format(Product::query()->whereColumn('stock', '<=', 'min_stock')->where('min_stock', '>', 0)->count(), 0, ',', '.'),
                    'note' => 'Perlu restok',
                    'tone' => 'text-error-600 bg-error-50 dark:bg-error-500/15',
                ],
            ],
            'monthlySalesChart' => [
                'labels' => $monthLabels,
                'series' => collect(range(1, 12))
                    ->map(fn (int $month): int => (int) ($monthlySales->get($month)['count'] ?? 0))
                    ->values(),
            ],
            'revenueChart' => [
                'labels' => $monthLabels,
                'revenue' => collect(range(1, 12))
                    ->map(fn (int $month): int => (int) ($monthlySales->get($month)['revenue'] ?? 0))
                    ->values(),
                'grossProfit' => collect(range(1, 12))
                    ->map(fn (int $month): int => (int) ($grossProfitByMonth[$month] ?? 0))
                    ->values(),
            ],
            'topProducts' => $topProducts,
        ];

        return view('pages.dashboard.ecommerce', [
            'title' => 'Dashboard',
            ...$dashboardData,
        ]);
    }

    private function grossProfitByMonth(Carbon $from, Carbon $to): array
    {
        $items = PosSaleItem::query()
            ->with(['sale', 'product', 'productVariant'])
            ->whereHas('sale', fn ($query) => $query->whereBetween('sold_at', [$from, $to]))
            ->get();

        return $items
            ->groupBy(fn (PosSaleItem $item): int => $item->sale->sold_at->month)
            ->map(fn ($group): int => $group->sum(fn (PosSaleItem $item): int => max(0, ($item->unit_price - $this->buyPrice($item)) * $item->quantity)))
            ->all();
    }

    private function topProducts($items)
    {
        return $items
            ->groupBy(fn (PosSaleItem $item): string => $item->product_id ? 'product-'.$item->product_id : 'item-'.$item->sku)
            ->map(function ($group): array {
                $first = $group->first();

                return [
                    'name' => $first->name,
                    'category' => $first->product?->category?->name ?? 'Umum',
                    'sold' => $group->sum('quantity'),
                    'revenue' => $group->sum('line_total'),
                ];
            })
            ->sortByDesc('sold')
            ->take(5)
            ->values()
            ->map(fn (array $product): array => [
                ...$product,
                'sold' => number_format($product['sold'], 0, ',', '.'),
                'revenue' => $this->formatRupiah($product['revenue']),
            ])
            ->all();
    }

    private function buyPrice(PosSaleItem $item): int
    {
        return (int) ($item->productVariant?->buy_price ?? $item->product?->buy_price ?? 0);
    }

    private function formatRupiah(int|float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    private function formatCompactRupiah(int|float $value): string
    {
        if ($value >= 1000000) {
            return 'Rp'.number_format($value / 1000000, 1, ',', '.').' jt';
        }

        return $this->formatRupiah($value);
    }
}
