<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('pages.products.index', [
            'title' => 'Produk',
            'products' => Product::query()
                ->with(['category', 'variants'])
                ->latest()
                ->get()
                ->map(fn (Product $product): array => $this->productPayload($product))
                ->values(),
            'categories' => ProductCategory::query()
                ->orderBy('name')
                ->get()
                ->map(fn (ProductCategory $category): array => $this->categoryPayload($category))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);

        $product = DB::transaction(function () use ($validated): Product {
            $product = Product::query()->create($this->productAttributes($validated));
            $this->syncVariants($product, $validated['variants'] ?? null);

            return $product;
        });

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'product' => $this->productPayload($product->load(['category', 'variants'])),
        ], 201);
    }

    public function update(Request $request, string $sku): JsonResponse
    {
        $validated = $this->validateProduct($request, $sku);

        $product = DB::transaction(function () use ($validated, $sku): Product {
            $product = Product::query()->firstOrNew(['sku' => $sku]);
            $product->fill($this->productAttributes($validated))->save();
            $this->syncVariants($product, $validated['variants'] ?? null);

            return $product;
        });

        return response()->json([
            'message' => "Produk {$sku} berhasil diperbarui.",
            'product' => $this->productPayload($product->load(['category', 'variants'])),
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

    private function productPayload(Product $product): array
    {
        $product->loadMissing('variants');

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category?->name ?? 'Umum',
            'barcode' => $product->barcode ?? '',
            'buyPrice' => $product->buy_price > 0 ? $this->formatRupiah($product->buy_price) : '',
            'stock' => $product->stock,
            'minStock' => $product->min_stock,
            'price' => $this->formatRupiah($product->sell_price),
            'status' => $this->productStatus($product->stock, $product->min_stock),
            'description' => $product->description ?? '',
            'variantCount' => $product->variants->count(),
            'variants' => $product->variants
                ->sortBy('id')
                ->map(fn (ProductVariant $variant): array => $this->variantPayload($variant))
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

    private function variantPayload(ProductVariant $variant): array
    {
        return [
            'name' => $variant->name,
            'sku' => $variant->sku ?? '',
            'barcode' => $variant->barcode ?? '',
            'unit' => $variant->unit ?? '',
            'unitConversion' => $variant->unit_conversion,
            'attributes' => $variant->attributes ?? [],
            'buyPrice' => $variant->buy_price > 0 ? $this->formatRupiah($variant->buy_price) : '',
            'sellPrice' => $this->formatRupiah($variant->sell_price),
            'stock' => $variant->stock,
            'minStock' => $variant->min_stock,
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
        return 'Rp' . number_format($value, 0, ',', '.');
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
