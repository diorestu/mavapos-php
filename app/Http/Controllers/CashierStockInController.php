<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryStockMovementService;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashierStockInController extends Controller
{
    public function index(): View
    {
        $branchId = app(BranchContext::class)->activeId();
        $manager = app(BranchInventoryManager::class);
        $items = Product::query()->with(['category', 'variants' => fn ($query) => $query->where('is_active', true)])->orderBy('name')->get()
            ->flatMap(function (Product $product) use ($branchId, $manager): array {
                if ($product->variants->isNotEmpty()) {
                    return $product->variants->map(fn (ProductVariant $variant): array => [
                        'sku' => $variant->sku ?? $product->sku.'-'.$variant->id,
                        'name' => $product->name.' · '.$variant->name,
                        'category' => $product->category?->name ?? 'Umum',
                        'barcode' => $variant->barcode ?? '',
                        'stock' => $manager->stockForVariant($branchId, $variant),
                        'isVariant' => true,
                    ])->all();
                }

                return [[
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? 'Umum',
                    'barcode' => $product->barcode ?? '',
                    'stock' => $manager->stockForProduct($branchId, $product),
                    'isVariant' => false,
                ]];
            })->values();

        return view('pages.inventory.cashier-stock-in', ['title' => 'Stok Masuk', 'items' => $items]);
    }

    public function store(Request $request, InventoryStockMovementService $service): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $service->record($validated['sku'], 'in', (int) $validated['quantity'], app(BranchContext::class)->activeId(), auth()->id(), $validated['reference'] ?? null, $validated['note'] ?? null);
        $item = [
            'sku' => $result['variant']?->sku ?? ($result['variant'] ? $result['product']->sku.'-'.$result['variant']->id : $result['product']->sku),
            'name' => $result['variant'] ? $result['product']->name.' · '.$result['variant']->name : $result['product']->name,
            'stock' => $result['stockAfter'],
            'isVariant' => (bool) $result['variant'],
        ];

        return response()->json(['message' => 'Stok masuk berhasil dicatat.', 'item' => $item, 'stockBefore' => $result['stockBefore'], 'stockAfter' => $result['stockAfter']], 201);
    }
}
