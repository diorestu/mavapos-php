<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RawMaterialController extends Controller
{
    private const UNITS = [
        'gram',
        'kg',
        'pcs',
        'ml',
        'liter',
        'box',
        'pack',
        'sendok',
    ];

    public function index(): View
    {
        $materials = RawMaterial::query()
            ->orderBy('name')
            ->get();

        return view('pages.raw-materials.index', [
            'title' => 'Inventory',
            'materials' => $materials,
            'units' => self::UNITS,
            'summary' => [
                'count' => $materials->count(),
                'stock_value' => $materials->sum(fn (RawMaterial $material): int => (int) ((float) $material->stock * $material->cost_per_unit)),
                'low_stock' => $materials
                    ->filter(fn (RawMaterial $material): bool => (float) $material->stock <= 0 || ((float) $material->min_stock > 0 && (float) $material->stock <= (float) $material->min_stock))
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('raw_materials', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:30'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        RawMaterial::query()->create([
            'code' => $validated['code'] ?: $this->nextCode(),
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'unit' => $validated['unit'],
            'stock' => $validated['stock'] ?? 0,
            'min_stock' => $validated['min_stock'] ?? 0,
            'cost_per_unit' => $validated['cost_per_unit'] ?? 0,
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()->route('raw-materials')->with('success', 'Bahan baku berhasil dicatat.');
    }

    private function nextCode(): string
    {
        $lastCode = RawMaterial::query()
            ->where('code', 'like', 'BB-%')
            ->latest('id')
            ->value('code');
        $sequence = $lastCode ? ((int) str($lastCode)->afterLast('-')->toString()) + 1 : 1;

        return 'BB-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
