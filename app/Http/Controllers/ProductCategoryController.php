<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(): View
    {
        return view('pages.product-categories.index', [
            'title' => 'Kategori Produk',
            'categories' => ProductCategory::query()
                ->orderBy('name')
                ->get()
                ->map(fn (ProductCategory $category): array => $this->categoryPayload($category))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCategory($request);
        $validated = $this->categoryAttributes($validated);

        $category = ProductCategory::query()->create($validated);

        return response()->json([
            'message' => 'Kategori produk berhasil dibuat.',
            'category' => $this->categoryPayload($category),
        ], 201);
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $validated = $this->validateCategory($request, $code);
        $validated = $this->categoryAttributes($validated);

        $category = ProductCategory::query()->firstOrNew(['code' => $code]);
        $category->fill($validated)->save();

        return response()->json([
            'message' => "Kategori produk {$code} berhasil diperbarui.",
            'category' => $this->categoryPayload($category->loadCount('products')),
        ]);
    }

    private function categoryAttributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'status' => $validated['status'],
            'product_count' => (int) ($validated['productCount'] ?? 0),
            'description' => $validated['description'] ?? null,
        ];
    }

    private function validateCategory(Request $request, ?string $currentCode = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('product_categories', 'code')->ignore($currentCode, 'code'),
            ],
            'status' => ['required', 'string', 'in:aktif,nonaktif'],
            'productCount' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function categoryPayload(ProductCategory $category): array
    {
        return [
            'name' => $category->name,
            'code' => $category->code,
            'status' => $this->statusLabel($category->status),
            'productCount' => $category->product_count,
            'description' => $category->description ?? '',
        ];
    }

    private function statusLabel(string $status): string
    {
        return [
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ][$status] ?? 'Aktif';
    }
}
