<?php

namespace App\Http\Controllers;

use App\Helpers\MenuHelper;
use App\Models\PosSale;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $keyword = trim($validated['q'] ?? '');

        if ($keyword === '') {
            return response()->json(['results' => []]);
        }

        return response()->json([
            'results' => collect()
                ->merge($this->menuResults($keyword))
                ->merge($this->productResults($keyword))
                ->merge($this->saleResults($keyword))
                ->take(12)
                ->values(),
        ]);
    }

    private function menuResults(string $keyword): array
    {
        $normalizedKeyword = Str::lower($keyword);

        return collect(MenuHelper::getMainNavItems())
            ->flatMap(function (array $item): array {
                if (! isset($item['subItems'])) {
                    return [[
                        'title' => $item['name'],
                        'subtitle' => 'Buka menu aplikasi',
                        'url' => url($item['path']),
                    ]];
                }

                return collect($item['subItems'])
                    ->map(fn (array $subItem): array => [
                        'title' => $subItem['name'],
                        'subtitle' => 'Menu '.$item['name'],
                        'url' => url($subItem['path']),
                    ])
                    ->prepend([
                        'title' => $item['name'],
                        'subtitle' => 'Grup menu aplikasi',
                        'url' => url($item['subItems'][0]['path'] ?? '/'),
                    ])
                    ->all();
            })
            ->filter(fn (array $item): bool => Str::contains(Str::lower($item['title'].' '.$item['subtitle']), $normalizedKeyword))
            ->map(fn (array $item): array => [
                'type' => 'menu',
                'label' => 'Menu',
                ...$item,
            ])
            ->values()
            ->all();
    }

    private function productResults(string $keyword): array
    {
        return Product::query()
            ->with('category')
            ->where(function ($query) use ($keyword): void {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%")
                    ->orWhere('barcode', 'like', "%{$keyword}%");
            })
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Product $product): array => [
                'type' => 'product',
                'label' => 'Produk',
                'title' => $product->name,
                'subtitle' => trim(($product->sku ?: '-').' · '.$this->formatRupiah($product->sell_price).' · Stok '.$product->stock),
                'url' => route('products', ['search' => $product->sku ?: $product->name]),
            ])
            ->all();
    }

    private function saleResults(string $keyword): array
    {
        return PosSale::query()->active()
            ->with(['user', 'items'])
            ->where(function ($query) use ($keyword): void {
                $query->where('invoice_number', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$keyword}%"))
                    ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('sku', 'like', "%{$keyword}%"));
            })
            ->latest('sold_at')
            ->limit(5)
            ->get()
            ->map(fn (PosSale $sale): array => [
                'type' => 'sale',
                'label' => 'Invoice',
                'title' => $sale->invoice_number,
                'subtitle' => trim(($sale->user?->name ?: 'Kasir').' · '.$this->formatRupiah($sale->total).' · '.$sale->sold_at?->format('d M Y H:i')),
                'url' => route('sales', [
                    'search' => $sale->invoice_number,
                    'date_from' => $sale->sold_at?->toDateString(),
                    'date_to' => $sale->sold_at?->toDateString(),
                ]),
            ])
            ->all();
    }

    private function formatRupiah(int|float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }
}
