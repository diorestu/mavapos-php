<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $categoryCodes = ProductCategory::query()->pluck('code')->all();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::in($categoryCodes)],
            'status' => ['nullable', 'string', Rule::in(['aktif', 'stok-menipis', 'habis'])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $branchId = app(BranchContext::class)->activeId();
        $products = Product::query()
            ->with(['category', 'variants'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $categoryCode) => $query
                ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('code', $categoryCode)))
            ->latest()
            ->get()
            ->map(fn (Product $product): array => $this->productPayload($product, $branchId))
            ->when($filters['status'] ?? null, fn ($collection, string $status) => $collection
                ->filter(fn (array $product): bool => match ($status) {
                    'habis' => $product['stock'] <= 0,
                    'stok-menipis' => $product['stock'] > 0 && $product['minStock'] > 0 && $product['stock'] <= $product['minStock'],
                    default => $product['stock'] > 0 && ($product['minStock'] <= 0 || $product['stock'] > $product['minStock']),
                }))
            ->values();

        return view('pages.products.index', [
            'title' => 'Produk',
            'products' => $products,
            'categories' => ProductCategory::query()
                ->orderBy('name')
                ->get()
                ->map(fn (ProductCategory $category): array => $this->categoryPayload($category))
                ->values(),
            'filters' => [
                'search' => $search,
                'category' => $filters['category'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);

        $product = DB::transaction(function () use ($validated): Product {
            $product = Product::query()->create($this->productAttributes($validated));
            $this->syncVariants($product, $validated['variants'] ?? null);
            $this->syncBranchInventories($product, $validated);

            return $product;
        });

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'product' => $this->productPayload($product->load(['category', 'variants']), app(BranchContext::class)->activeId()),
        ], 201);
    }

    public function update(Request $request, string $sku): JsonResponse
    {
        $validated = $this->validateProduct($request, $sku);

        $product = DB::transaction(function () use ($validated, $sku): Product {
            $product = Product::query()->firstOrNew(['sku' => $sku]);
            $product->fill($this->productAttributes($validated))->save();
            $this->syncVariants($product, $validated['variants'] ?? null);
            $this->syncBranchInventories($product, $validated);

            return $product;
        });

        return response()->json([
            'message' => "Produk {$sku} berhasil diperbarui.",
            'product' => $this->productPayload($product->load(['category', 'variants']), app(BranchContext::class)->activeId()),
        ]);
    }

    public function destroy(string $sku): JsonResponse
    {
        $product = Product::query()
            ->with('variants')
            ->where('sku', $sku)
            ->firstOrFail();

        DB::transaction(function () use ($product): void {
            $variantIds = $product->variants->pluck('id');

            $product->branchInventories()->delete();

            if ($variantIds->isNotEmpty()) {
                DB::table('branch_inventories')
                    ->whereIn('product_variant_id', $variantIds)
                    ->delete();
            }

            $product->delete();
        });

        return response()->json([
            'message' => "Produk {$sku} berhasil dihapus.",
        ]);
    }

    private function validateProduct(Request $request, ?string $currentSku = null): array
    {
        $categoryCodes = ProductCategory::query()->pluck('code')->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($currentSku, 'sku'),
            ],
            'category' => ['nullable', 'string', Rule::in($categoryCodes)],
            'barcode' => ['nullable', 'string', 'max:100'],
            'buyPrice' => ['nullable', 'numeric', 'min:0'],
            'sellPrice' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'minStock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:150'],
            'variants.*.sku' => ['nullable', 'string', 'max:80'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.unit' => ['nullable', 'string', 'max:40'],
            'variants.*.unitConversion' => ['nullable', 'integer', 'min:1'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.buyPrice' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sellPrice' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.minStock' => ['nullable', 'integer', 'min:0'],
            'variants.*.isActive' => ['nullable', 'boolean'],
            'variants.*.isFavorite' => ['nullable', 'boolean'],
            'variants.*.isTaxable' => ['nullable', 'boolean'],
            'variants.*.isDiscountable' => ['nullable', 'boolean'],
            'variants.*.servingTimeMinutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'variants.*.availableForDineIn' => ['nullable', 'boolean'],
            'variants.*.availableForTakeaway' => ['nullable', 'boolean'],
        ]);
    }

    private function productAttributes(array $validated): array
    {
        $category = ProductCategory::query()->firstWhere('code', $validated['category'] ?? null);

        return [
            'product_category_id' => $category?->id,
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'barcode' => $validated['barcode'] ?? null,
            'buy_price' => (int) ($validated['buyPrice'] ?? 0),
            'sell_price' => (int) $validated['sellPrice'],
            'stock' => (int) ($validated['stock'] ?? 0),
            'min_stock' => (int) ($validated['minStock'] ?? 0),
            'description' => $validated['description'] ?? null,
        ];
    }

    private function productPayload(Product $product, int $branchId): array
    {
        $product->loadMissing('variants');
        $inventory = app(BranchInventoryManager::class)->forProduct($branchId, $product);

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'categoryCode' => $product->category?->code ?? '',
            'category' => $product->category?->name ?? 'Umum',
            'barcode' => $product->barcode ?? '',
            'buyPrice' => $product->buy_price > 0 ? $this->formatRupiah($product->buy_price) : '',
            'stock' => $inventory->stock,
            'minStock' => $inventory->min_stock,
            'price' => $this->formatRupiah($product->sell_price),
            'status' => $this->productStatus($inventory->stock, $inventory->min_stock),
            'description' => $product->description ?? '',
            'variantCount' => $product->variants->count(),
            'variants' => $product->variants
                ->sortBy('id')
                ->map(fn (ProductVariant $variant): array => $this->variantPayload($variant, $branchId))
                ->values(),
        ];
    }

    private function syncVariants(Product $product, ?array $variants): void
    {
        if ($variants === null) {
            return;
        }

        $product->variants()->delete();

        foreach ($variants as $variant) {
            $product->variants()->create([
                'name' => $variant['name'],
                'sku' => $variant['sku'] ?? null,
                'barcode' => $variant['barcode'] ?? null,
                'unit' => $variant['unit'] ?? null,
                'unit_conversion' => (int) ($variant['unitConversion'] ?? 1),
                'attributes' => $variant['attributes'] ?? null,
                'buy_price' => (int) ($variant['buyPrice'] ?? 0),
                'sell_price' => (int) $variant['sellPrice'],
                'stock' => (int) ($variant['stock'] ?? 0),
                'min_stock' => (int) ($variant['minStock'] ?? 0),
                'is_active' => $variant['isActive'] ?? true,
                'is_favorite' => $variant['isFavorite'] ?? false,
                'is_taxable' => $variant['isTaxable'] ?? false,
                'is_discountable' => $variant['isDiscountable'] ?? true,
                'serving_time_minutes' => $variant['servingTimeMinutes'] ?? null,
                'available_for_dine_in' => $variant['availableForDineIn'] ?? true,
                'available_for_takeaway' => $variant['availableForTakeaway'] ?? true,
            ]);
        }
    }

    private function syncBranchInventories(Product $product, array $validated): void
    {
        $branchId = app(BranchContext::class)->activeId();
        $inventoryManager = app(BranchInventoryManager::class);
        $inventoryManager->forProduct($branchId, $product)->update([
            'stock' => (int) ($validated['stock'] ?? 0),
            'min_stock' => (int) ($validated['minStock'] ?? 0),
        ]);

        $variantPayloads = collect($validated['variants'] ?? []);
        $product->load('variants');

        $product->variants
            ->sortBy('id')
            ->values()
            ->each(function (ProductVariant $variant, int $index) use ($branchId, $inventoryManager, $variantPayloads): void {
                $payload = $variantPayloads->get($index, []);

                $inventoryManager->forVariant($branchId, $variant)->update([
                    'stock' => (int) ($payload['stock'] ?? 0),
                    'min_stock' => (int) ($payload['minStock'] ?? 0),
                ]);
            });
    }

    private function variantPayload(ProductVariant $variant, int $branchId): array
    {
        $inventory = app(BranchInventoryManager::class)->forVariant($branchId, $variant);

        return [
            'name' => $variant->name,
            'sku' => $variant->sku ?? '',
            'barcode' => $variant->barcode ?? '',
            'unit' => $variant->unit ?? '',
            'unitConversion' => $variant->unit_conversion,
            'attributes' => $variant->attributes ?? [],
            'buyPrice' => $variant->buy_price > 0 ? $this->formatRupiah($variant->buy_price) : '',
            'sellPrice' => $this->formatRupiah($variant->sell_price),
            'stock' => $inventory->stock,
            'minStock' => $inventory->min_stock,
            'isActive' => $variant->is_active,
            'isFavorite' => $variant->is_favorite,
            'isTaxable' => $variant->is_taxable,
            'isDiscountable' => $variant->is_discountable,
            'servingTimeMinutes' => $variant->serving_time_minutes,
            'availableForDineIn' => $variant->available_for_dine_in,
            'availableForTakeaway' => $variant->available_for_takeaway,
        ];
    }

    private function categoryPayload(ProductCategory $category): array
    {
        return [
            'code' => $category->code,
            'name' => $category->name,
            'productCount' => $category->product_count,
            'status' => $this->statusLabel($category->status),
            'description' => $category->description ?? '',
        ];
    }

    private function formatRupiah(int|float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    private function productStatus(int $stock, int $minStock): string
    {
        if ($stock <= 0) {
            return 'Habis';
        }

        if ($minStock > 0 && $stock <= $minStock) {
            return 'Stok Menipis';
        }

        return 'Aktif';
    }

    private function statusLabel(string $status): string
    {
        return [
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ][$status] ?? 'Aktif';
    }
}
