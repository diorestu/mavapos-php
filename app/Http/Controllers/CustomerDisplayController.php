<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CustomerDisplayController extends Controller
{
    private const CACHE_TTL_SECONDS = 60;
    private const CACHE_KEY_PREFIX = 'pos:display:';

    public function push(Request $request, BranchContext $branchContext): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:cart,checkout'],
            'cart' => ['nullable', 'array'],
            'cart.*.name' => ['required_with:cart', 'string', 'max:120'],
            'cart.*.quantity' => ['required_with:cart', 'integer', 'min:1'],
            'cart.*.line_total' => ['required_with:cart', 'integer', 'min:0'],
            'subtotal' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'total' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:20'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'change_amount' => ['nullable', 'integer', 'min:0'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
        ]);

        $state = [
            'mode' => $validated['mode'],
            'cart' => $validated['cart'] ?? [],
            'subtotal' => (int) ($validated['subtotal'] ?? 0),
            'discount' => (int) ($validated['discount'] ?? 0),
            'total' => (int) ($validated['total'] ?? 0),
            'payment_method' => $validated['payment_method'] ?? null,
            'paid_amount' => isset($validated['paid_amount']) ? (int) $validated['paid_amount'] : null,
            'change_amount' => isset($validated['change_amount']) ? (int) $validated['change_amount'] : null,
            'invoice_number' => $validated['invoice_number'] ?? null,
            'store_name' => StoreSetting::current()->store_name,
            'updated_at' => now()->toIso8601String(),
        ];

        $key = $this->cacheKey($branchContext->activeId());
        Cache::put($key, $state, self::CACHE_TTL_SECONDS);

        return response()->json(['ok' => true]);
    }

    public function show(Request $request, BranchContext $branchContext): View
    {
        return view('pages.display.stand', [
            'title' => 'Display Pelanggan',
            'storeName' => StoreSetting::current()->store_name,
        ]);
    }

    public function state(BranchContext $branchContext): JsonResponse
    {
        $state = Cache::get($this->cacheKey($branchContext->activeId())) ?? $this->emptyState();

        return response()->json($state);
    }

    private function cacheKey(int $branchId): string
    {
        return self::CACHE_KEY_PREFIX.$branchId;
    }

    private function emptyState(): array
    {
        return [
            'mode' => 'cart',
            'cart' => [],
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_method' => null,
            'paid_amount' => null,
            'change_amount' => null,
            'invoice_number' => null,
            'store_name' => StoreSetting::current()->store_name,
            'updated_at' => null,
        ];
    }
}
