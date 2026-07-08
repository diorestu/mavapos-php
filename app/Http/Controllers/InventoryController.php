<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['aktif', 'stok-menipis', 'habis'])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $activeBranch = app(BranchContext::class)->active();
        $branchId = $activeBranch->id;
        $items = Product::query()
            ->with(['category', 'variants' => function ($query) {
                $query->where('is_active', true);
            }])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('variants', fn ($variantQuery) => $variantQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => $this->payload($product, $branchId))
            ->when($filters['status'] ?? null, fn ($collection, string $status) => $collection
                ->filter(fn (array $item): bool => match ($status) {
                    'habis' => $item['stock'] <= 0,
                    'stok-menipis' => $item['stock'] > 0 && $item['minStock'] > 0 && $item['stock'] <= $item['minStock'],
                    default => $item['stock'] > 0 && ($item['minStock'] <= 0 || $item['stock'] > $item['minStock']),
                }))
            ->values();

        return view('pages.inventory.index', [
            'title' => 'Stok',
            'items' => $items,
            'movements' => StockMovement::query()
                ->with(['product', 'productVariant'])
                ->where('branch_id', $branchId)
                ->latest('occurred_at')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (StockMovement $movement): array => $this->movementPayload($movement))
                ->values(),
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? '',
            ],
            'activeBranch' => $activeBranch,
        ]);
    }

    private function resolveSku(string $sku): ?array
    {
        $variant = ProductVariant::query()->where('sku', $sku)->whereHas('product')->first();

        if ($variant) {
            return ['type' => 'variant', 'model' => $variant];
        }

        if (Str::contains($sku, '-')) {
            $variantId = (int) Str::afterLast($sku, '-');
            $parentSku = Str::beforeLast($sku, '-');

            $variant = ProductVariant::query()
                ->where('id', $variantId)
                ->whereHas('product', fn ($query) => $query->where('sku', $parentSku))
                ->first();

            if ($variant) {
                return ['type' => 'variant', 'model' => $variant];
            }
        }

        $product = Product::query()->where('sku', $sku)->first();

        if ($product) {
            return ['type' => 'product', 'model' => $product];
        }

        return null;
    }

    public function update(Request $request, string $sku): JsonResponse
    {
        $validated = $request->validate([
            'stock' => ['required', 'integer', 'min:0'],
            'minStock' => ['nullable', 'integer', 'min:0'],
        ]);

        $branchId = app(BranchContext::class)->activeId();
        $resolved = $this->resolveSku($sku);

        if (! $resolved) {
            abort(404, 'Item tidak ditemukan.');
        }

        if ($resolved['type'] === 'variant') {
            $variant = $resolved['model'];
            $inventory = app(BranchInventoryManager::class)->forVariant($branchId, $variant);
            $inventory->update([
                'stock' => (int) $validated['stock'],
                'min_stock' => (int) ($validated['minStock'] ?? 0),
            ]);
            $variant->update([
                'stock' => $inventory->stock,
                'min_stock' => $inventory->min_stock,
            ]);

            return response()->json([
                'message' => "Stok varian {$variant->name} berhasil diperbarui.",
                'item' => $this->payload($variant->product->load('category'), $branchId),
            ]);
        }

        $product = $resolved['model'];
        $inventory = app(BranchInventoryManager::class)->forProduct($branchId, $product);
        $inventory->update([
            'stock' => (int) $validated['stock'],
            'min_stock' => (int) ($validated['minStock'] ?? 0),
        ]);
        $product->update([
            'stock' => $inventory->stock,
            'min_stock' => $inventory->min_stock,
        ]);

        return response()->json([
            'message' => "Stok {$sku} berhasil diperbarui.",
            'item' => $this->payload($product->load('category'), $branchId),
        ]);
    }

    public function storeIn(Request $request, string $sku): JsonResponse
    {
        return $this->storeMovement($request, $sku, 'in');
    }

    public function storeOut(Request $request, string $sku): JsonResponse
    {
        return $this->storeMovement($request, $sku, 'out');
    }

    private function payload(Product $product, int $branchId): array
    {
        $inventory = $product->branchInventories()
            ->where('branch_id', $branchId)
            ->whereNull('product_variant_id')
            ->first();
        $inventoryManager = app(BranchInventoryManager::class);

        $hasVariants = $product->variants()->where('is_active', true)->exists();

        if ($hasVariants) {
            $variants = $product->variants()
                ->where('is_active', true)
                ->get()
                ->map(function (ProductVariant $variant) use ($product, $branchId, $inventoryManager): array {
                    $variantInventory = $variant->branchInventories()
                        ->where('branch_id', $branchId)
                        ->first();
                    $vStock = $inventoryManager->forVariant($branchId, $variant)->stock;
                    $vMinStock = $inventoryManager->forVariant($branchId, $variant)->min_stock;
                    return [
                        'name' => $variant->name,
                        'fullName' => $product->name . ' - ' . $variant->name,
                        'sku' => $variant->sku ?? $product->sku . '-' . $variant->id,
                        'category' => $product->category?->name ?? 'Umum',
                        'stock' => $vStock,
                        'minStock' => $vMinStock,
                        'status' => $this->stockStatus($vStock, $vMinStock),
                        'updatedAt' => $variantInventory?->updated_at?->format('d M Y H:i') ?? '-',
                        'isVariant' => true,
                    ];
                });

            $totalStock = $variants->sum('stock');
            $totalMinStock = $variants->sum('minStock');
        } else {
            $variants = collect();
            $totalStock = $inventoryManager->stockForProduct($branchId, $product);
            $totalMinStock = $inventoryManager->minStockForProduct($branchId, $product);
        }

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category?->name ?? 'Umum',
            'stock' => $totalStock,
            'minStock' => $totalMinStock,
            'status' => $this->stockStatus($totalStock, $totalMinStock),
            'updatedAt' => $inventory?->updated_at?->format('d M Y H:i') ?? '-',
            'hasVariants' => $hasVariants,
            'variants' => $variants->values()->all(),
            'isVariant' => false,
        ];
    }

    private function stockStatus(int $stock, int $minStock): string
    {
        if ($stock <= 0) {
            return 'Habis';
        }

        if ($minStock > 0 && $stock <= $minStock) {
            return 'Stok Menipis';
        }

        return 'Aktif';
    }

    private function storeMovement(Request $request, string $sku, string $type): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'occurredAt' => ['nullable', 'date'],
        ]);
        $branchId = app(BranchContext::class)->activeId();

        $resolved = $this->resolveSku($sku);

        if (! $resolved) {
            abort(404, 'Item tidak ditemukan.');
        }

        $result = DB::transaction(function () use ($validated, $resolved, $type, $branchId): array {
            if ($resolved['type'] === 'variant') {
                $variant = $resolved['model'];
                $product = $variant->product;
                $inventory = app(BranchInventoryManager::class)->forVariant($branchId, $variant, true);

                $quantity = (int) $validated['quantity'];
                $stockBefore = $inventory->stock;

                if ($type === 'out' && $quantity > $stockBefore) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok keluar melebihi stok tersedia.',
                    ]);
                }

                $stockAfter = $type === 'in'
                    ? $stockBefore + $quantity
                    : $stockBefore - $quantity;

                $inventory->update([
                    'stock' => $stockAfter,
                ]);
                $variant->update([
                    'stock' => $stockAfter,
                ]);

                $movement = StockMovement::query()->create([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'type' => $type,
                    'quantity' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'reference' => $validated['reference'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'occurred_at' => $validated['occurredAt'] ?? now(),
                ]);

                return [
                    'product' => $product->load('category'),
                    'movement' => $movement->load(['product', 'productVariant']),
                ];
            }

            $product = $resolved['model'];
            $inventory = app(BranchInventoryManager::class)->forProduct($branchId, $product, true);

            $quantity = (int) $validated['quantity'];
            $stockBefore = $inventory->stock;

            if ($type === 'out' && $quantity > $stockBefore) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok keluar melebihi stok tersedia.',
                ]);
            }

            $stockAfter = $type === 'in'
                ? $stockBefore + $quantity
                : $stockBefore - $quantity;

            $inventory->update([
                'stock' => $stockAfter,
                'min_stock' => $inventory->min_stock,
            ]);
            $product->update([
                'stock' => $stockAfter,
            ]);

            $movement = StockMovement::query()->create([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_variant_id' => null,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference' => $validated['reference'] ?? null,
                'note' => $validated['note'] ?? null,
                'occurred_at' => $validated['occurredAt'] ?? now(),
            ]);

            return [
                'product' => $product->load('category'),
                'movement' => $movement->load('product'),
            ];
        });

        return response()->json([
            'message' => $type === 'in'
                ? 'Stok masuk berhasil dicatat.'
                : 'Stok keluar berhasil dicatat.',
            'item' => $this->payload($result['product'], $branchId),
            'movement' => $this->movementPayload($result['movement']),
        ], 201);
    }

    private function movementPayload(StockMovement $movement): array
    {
        $sku = $movement->productVariant
            ? ($movement->productVariant->sku ?? ($movement->product?->sku . '-' . $movement->productVariant->id))
            : ($movement->product?->sku ?? '-');

        $productName = $movement->product?->name ?? '-';
        if ($movement->productVariant) {
            $productName .= ' - ' . $movement->productVariant->name;
        }

        return [
            'id' => $movement->id,
            'type' => $movement->type === 'in' ? 'Masuk' : 'Keluar',
            'typeCode' => $movement->type,
            'sku' => $sku,
            'product' => $productName,
            'quantity' => $movement->quantity,
            'stockBefore' => $movement->stock_before,
            'stockAfter' => $movement->stock_after,
            'reference' => $movement->reference ?: '-',
            'note' => $movement->note ?: '-',
            'occurredAt' => $movement->occurred_at?->format('d M Y H:i') ?? '-',
        ];
    }
}
