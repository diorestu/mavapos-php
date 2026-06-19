<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
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

        return view('pages.inventory.index', [
            'title' => 'Stok',
            'items' => Product::query()
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
                ->when($filters['status'] ?? null, fn ($query, string $status) => $this->applyStockStatusFilter($query, $status))
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product): array => $this->payload($product))
                ->values(),
            'movements' => StockMovement::query()
                ->with('product')
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
        ]);
    }

    public function update(Request $request, string $sku): JsonResponse
    {
        $validated = $request->validate([
            'stock' => ['required', 'integer', 'min:0'],
            'minStock' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = Product::query()->where('sku', $sku)->firstOrFail();
        $product->update([
            'stock' => (int) $validated['stock'],
            'min_stock' => (int) ($validated['minStock'] ?? 0),
        ]);

        return response()->json([
            'message' => "Stok {$sku} berhasil diperbarui.",
            'item' => $this->payload($product->load('category')),
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

    private function payload(Product $product): array
    {
        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category?->name ?? 'Umum',
            'stock' => $product->stock,
            'minStock' => $product->min_stock,
            'status' => $this->stockStatus($product->stock, $product->min_stock),
            'updatedAt' => $product->updated_at?->format('d M Y H:i') ?? '-',
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

    private function applyStockStatusFilter($query, string $status): void
    {
        match ($status) {
            'habis' => $query->where('stock', '<=', 0),
            'stok-menipis' => $query
                ->where('stock', '>', 0)
                ->where('min_stock', '>', 0)
                ->whereColumn('stock', '<=', 'min_stock'),
            default => $query
                ->where('stock', '>', 0)
                ->where(function ($nested): void {
                    $nested->where('min_stock', '<=', 0)
                        ->orWhereColumn('stock', '>', 'min_stock');
                }),
        };
    }

    private function storeMovement(Request $request, string $sku, string $type): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'occurredAt' => ['nullable', 'date'],
        ]);

        $result = DB::transaction(function () use ($validated, $sku, $type): array {
            $product = Product::query()
                ->where('sku', $sku)
                ->lockForUpdate()
                ->firstOrFail();

            $quantity = (int) $validated['quantity'];
            $stockBefore = $product->stock;

            if ($type === 'out' && $quantity > $stockBefore) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok keluar melebihi stok tersedia.',
                ]);
            }

            $stockAfter = $type === 'in'
                ? $stockBefore + $quantity
                : $stockBefore - $quantity;

            $product->update([
                'stock' => $stockAfter,
            ]);

            $movement = StockMovement::query()->create([
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
            'item' => $this->payload($result['product']),
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
