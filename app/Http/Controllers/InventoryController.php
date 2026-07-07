<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->with('category')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"));
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
                ->with('product')
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

    public function update(Request $request, string $sku): JsonResponse
    {
        $validated = $request->validate([
            'stock' => ['required', 'integer', 'min:0'],
            'minStock' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = Product::query()->where('sku', $sku)->firstOrFail();
        $branchId = app(BranchContext::class)->activeId();
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
        $stock = $inventoryManager->stockForProduct($branchId, $product);
        $minStock = $inventoryManager->minStockForProduct($branchId, $product);

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category?->name ?? 'Umum',
            'stock' => $stock,
            'minStock' => $minStock,
            'status' => $this->stockStatus($stock, $minStock),
            'updatedAt' => $inventory?->updated_at?->format('d M Y H:i') ?? '-',
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

        $result = DB::transaction(function () use ($validated, $sku, $type, $branchId): array {
            $product = Product::query()
                ->where('sku', $sku)
                ->lockForUpdate()
                ->firstOrFail();
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
            ]);
            $product->update([
                'stock' => $stockAfter,
            ]);

            $movement = StockMovement::query()->create([
                'branch_id' => $branchId,
                'product_id' => $product->id,
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
        return [
            'id' => $movement->id,
            'type' => $movement->type === 'in' ? 'Masuk' : 'Keluar',
            'typeCode' => $movement->type,
            'sku' => $movement->product?->sku ?? '-',
            'product' => $movement->product?->name ?? '-',
            'quantity' => $movement->quantity,
            'stockBefore' => $movement->stock_before,
            'stockAfter' => $movement->stock_after,
            'reference' => $movement->reference ?: '-',
            'note' => $movement->note ?: '-',
            'occurredAt' => $movement->occurred_at?->format('d M Y H:i') ?? '-',
        ];
    }
}
