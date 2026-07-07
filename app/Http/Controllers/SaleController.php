<?php

namespace App\Http\Controllers;

use App\Models\PosSale;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', 'in:cash,qris,card'],
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
            ->with(['user', 'branch', 'shift.user', 'items'])
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

        $summaryQuery = clone $baseQuery;
        $summary = $summaryQuery
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(total), 0) as net_sales')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN total ELSE 0 END), 0) as qris_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_total")
            ->first();

        $sales = $baseQuery
            ->latest('sold_at')
            ->paginate(12)
            ->withQueryString();

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
                'sales_count' => (int) $summary->sales_count,
                'gross_sales' => (int) $summary->gross_sales,
                'discount_total' => (int) $summary->discount_total,
                'net_sales' => (int) $summary->net_sales,
                'cash_total' => (int) $summary->cash_total,
                'qris_total' => (int) $summary->qris_total,
                'card_total' => (int) $summary->card_total,
            ],
        ]);
    }
}
