<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductRecipeController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->with(['category', 'recipeItems.rawMaterial'])
            ->orderBy('name')
            ->get();
        $rawMaterials = RawMaterial::query()->orderBy('name')->get();

        return view('pages.product-recipes.index', [
            'title' => 'Resep Produk',
            'products' => $products,
            'rawMaterials' => $rawMaterials,
            'rawMaterialPayload' => $rawMaterials
                ->map(fn (RawMaterial $material): array => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'code' => $material->code,
                    'unit' => $material->unit,
                    'stock' => rtrim(rtrim((string) $material->stock, '0'), '.'),
                ])
                ->values(),
            'recipePayload' => $products
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name ?? 'Umum',
                    'items' => $product->recipeItems
                        ->sortBy('id')
                        ->map(fn ($item): array => [
                            'raw_material_id' => $item->raw_material_id,
                            'item_name' => $item->item_name,
                            'quantity' => rtrim(rtrim((string) $item->quantity, '0'), '.'),
                            'unit' => $item->unit,
                        ])
                        ->values(),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.raw_material_id' => ['required', 'integer', Rule::exists('raw_materials', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        DB::transaction(function () use ($validated): void {
            $product = Product::query()->findOrFail($validated['product_id']);
            $product->recipeItems()->delete();

            foreach ($validated['items'] as $item) {
                $rawMaterial = RawMaterial::query()->findOrFail($item['raw_material_id']);

                $product->recipeItems()->create([
                    'raw_material_id' => $rawMaterial->id,
                    'item_name' => $rawMaterial->name,
                    'quantity' => $item['quantity'],
                    'unit' => $rawMaterial->unit,
                ]);
            }
        });

        return redirect()->route('product-recipes')->with('success', 'Resep produk berhasil disimpan.');
    }
}
