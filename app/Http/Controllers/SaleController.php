<?php

namespace App\Http\Controllers;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminSaleEditorService;
use App\Services\SalesBonusService;
use App\Services\TransactionVoidService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function edit(PosSale $sale): View
    {
        $branchId = app(BranchContext::class)->activeId();
        $sale = PosSale::query()->with('items')->whereKey($sale->id)->where('branch_id', $branchId)->firstOrFail();
        abort_if($sale->voided_at, 422, 'Transaksi yang sudah di-void tidak dapat diedit.');

        $items = Product::query()->with(['variants' => fn ($query) => $query->where('is_active', true)])->orderBy('name')->get()
            ->flatMap(function (Product $product) {
                return collect([['id' => 'product-'.$product->sku, 'name' => $product->name, 'price' => $product->sell_price]])
                    ->merge($product->variants->map(fn ($variant) => ['id' => 'variant-'.$variant->id, 'name' => $product->name.' · '.$variant->name, 'price' => $product->sell_price + $variant->sell_price]));
            })->values();

        return view('pages.sales.edit', compact('sale', 'items') + ['title' => 'Edit Transaksi']);
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', 'in:cash,qris,card,free'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $from = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : now()->startOfDay();
        $to = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();
        $branchId = app(BranchContext::class)->activeId();

        $baseQuery = PosSale::query()
            ->with(['user', 'branch', 'shift.user', 'items', 'voidedBy'])
            ->where('branch_id', $branchId)
            ->whereBetween('sold_at', [$from, $to])
            ->when($validated['cashier_id'] ?? null, fn ($query, $cashierId) => $query->where('user_id', $cashierId))
            ->when($validated['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"));
                });
            });

        $summaryQuery = (clone $baseQuery)->active();
        $summary = $summaryQuery
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(total), 0) as net_sales')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN total ELSE 0 END), 0) as qris_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_total")
            ->first();
        $summarySaleIds = (clone $baseQuery)->active()->pluck('id');
        $soldUnits = (int) PosSaleItem::query()->whereIn('pos_sale_id', $summarySaleIds)->sum('quantity')
            + (int) PosSale::query()->whereIn('id', $summarySaleIds)->doesntHave('items')->count();
        $buyerNationalityCounts = PosSale::query()->whereIn('id', $summarySaleIds)
            ->selectRaw('buyer_nationality, COUNT(*) as buyer_count')
            ->groupBy('buyer_nationality')
            ->pluck('buyer_count', 'buyer_nationality');

        $sales = $baseQuery
            ->latest('sold_at')
            ->paginate(12)
            ->withQueryString();

        $bonus = app(SalesBonusService::class)->forBranchDay($branchId, $from);
        $bonus['staff'] = User::query()->whereIn('id', $bonus['staffIds'])->orderBy('name')->get(['id', 'name']);
        $bonus['staffBreakdown'] = collect($bonus['staffBreakdown'])->map(function (array $row) use ($bonus): array {
            $row['name'] = $bonus['staff']->firstWhere('id', $row['userId'])?->name ?? 'Staff';

            return $row;
        })->values();

        return view('pages.sales.index', [
            'title' => 'Penjualan',
            'sales' => $sales,
            'cashiers' => User::query()
                ->whereHas('posSales', fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'cashier_id' => $validated['cashier_id'] ?? '',
                'payment_method' => $validated['payment_method'] ?? '',
                'search' => $validated['search'] ?? '',
            ],
            'summary' => [
                'sales_count' => $soldUnits,
                'gross_sales' => (int) $summary->gross_sales,
                'discount_total' => (int) $summary->discount_total,
                'net_sales' => (int) $summary->net_sales,
                'cash_total' => (int) $summary->cash_total,
                'qris_total' => (int) $summary->qris_total,
                'card_total' => (int) $summary->card_total,
                'local_buyers' => (int) ($buyerNationalityCounts->get('local') ?? 0),
                'foreigner_buyers' => (int) ($buyerNationalityCounts->get('foreigner') ?? 0),
            ],
            'bonus' => $bonus,
        ]);
    }

    public function void(Request $request, PosSale $sale, TransactionVoidService $service): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $sale = $service->void($sale, app(BranchContext::class)->activeId(), $request->user(), $validated['reason']);

        return response()->json([
            'message' => 'Transaksi '.$sale->invoice_number.' berhasil dibatalkan.',
            'sale' => ['id' => $sale->id, 'status' => 'voided', 'voidReason' => $sale->void_reason],
        ]);
    }

    public function update(Request $request, PosSale $sale, AdminSaleEditorService $service): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:cash,qris,card,free'],
            'complimentary_category' => ['required_if:payment_method,free', 'nullable', 'in:influencer,partnership,owner'],
            'complimentary_recipient_name' => ['required_if:payment_method,free', 'nullable', 'string', 'max:150'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'buyer_nationality' => ['nullable', 'in:local,foreigner'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $sale = $service->update($sale, app(BranchContext::class)->activeId(), $request->user(), $validated);

        return response()->json([
            'message' => 'Transaksi '.$sale->invoice_number.' berhasil diperbarui.',
            'sale' => ['id' => $sale->id, 'subtotal' => $sale->subtotal, 'total' => $sale->total],
        ]);
    }
}
