<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Services\InventoryStockMovementService;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                        'type' => 'product',
                    ])->all();
                }

                return [[
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? 'Umum',
                    'barcode' => $product->barcode ?? '',
                    'stock' => $manager->stockForProduct($branchId, $product),
                    'isVariant' => false,
                    'type' => 'product',
                ]];
            })
            ->concat(RawMaterial::query()->orderBy('name')->get()->map(fn (RawMaterial $material): array => [
                'stockItem' => 'raw-material-'.$material->id,
                'sku' => $material->code,
                'name' => $material->name,
                'category' => $material->category ?: 'Bahan Baku',
                'barcode' => '',
                'stock' => (float) $material->stock,
                'unit' => $material->unit,
                'isVariant' => false,
                'type' => 'raw_material',
            ]))
            ->values();

        return view('pages.inventory.cashier-stock-in', ['title' => 'Stok Masuk', 'items' => $items]);
    }

    public function store(Request $request, InventoryStockMovementService $service): JsonResponse
    {
        $validated = $request->validate([
            'stock_item' => ['nullable', 'string', 'regex:/^raw-material-[0-9]+$/', 'required_without:sku'],
            'sku' => ['nullable', 'string', 'max:100', 'required_without:stock_item'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($validated['stock_item'])) {
            $materialId = (int) Str::after($validated['stock_item'], 'raw-material-');
            $result = DB::transaction(function () use ($materialId, $validated): array {
                $material = RawMaterial::query()->whereKey($materialId)->lockForUpdate()->firstOrFail();
                $stockBefore = (float) $material->stock;
                $stockAfter = $stockBefore + (float) $validated['quantity'];

                $material->update([
                    'stock' => $stockAfter,
                    'note' => $validated['note'] ?? $material->note,
                ]);

                return compact('material', 'stockBefore', 'stockAfter');
            });

            return response()->json([
                'message' => 'Stok bahan baku berhasil dicatat.',
                'item' => [
                    'stockItem' => 'raw-material-'.$result['material']->id,
                    'sku' => $result['material']->code,
                    'name' => $result['material']->name,
                    'stock' => $result['stockAfter'],
                    'unit' => $result['material']->unit,
                    'type' => 'raw_material',
                    'isVariant' => false,
                ],
                'stockBefore' => $result['stockBefore'],
                'stockAfter' => $result['stockAfter'],
            ], 201);
        }

        $request->validate(['quantity' => ['integer', 'min:1']]);
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
