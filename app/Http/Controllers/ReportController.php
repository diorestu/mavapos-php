<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\Expense;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.reports.index', [
            'title' => 'Laporan',
            ...$this->reportData($request),
        ]);
    }

    public function download(Request $request): Response
    {
        $data = $this->reportData($request);
        $fileName = sprintf(
            'laporan-%s-sampai-%s.pdf',
            $data['period']['from']->format('Y-m-d'),
            $data['period']['to']->format('Y-m-d'),
        );

        return Pdf::loadView('pages.reports.pdf', [
            'title' => 'Laporan',
            ...$data,
        ])
            ->setPaper('a4')
            ->download($fileName);
    }

    private function reportData(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : now()->startOfMonth();
        $to = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();
        $activeBranch = app(BranchContext::class)->active();

        $inventoryManager = app(BranchInventoryManager::class);
        $products = Product::query()
            ->with('category')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($inventoryManager, $activeBranch): Product {
                $product->stock = $inventoryManager->stockForProduct($activeBranch->id, $product);
                $product->min_stock = $inventoryManager->minStockForProduct($activeBranch->id, $product);

                return $product;
            });
        $billingQuery = Billing::query()->whereBetween('created_at', [$from, $to]);
        $posSaleQuery = PosSale::query()
            ->where('branch_id', $activeBranch->id)
            ->whereBetween('sold_at', [$from, $to]);
        $expenseQuery = Expense::query()
            ->where('branch_id', $activeBranch->id)
            ->whereBetween('spent_at', [$from, $to]);
        $movementQuery = StockMovement::query()
            ->where('branch_id', $activeBranch->id)
            ->whereBetween('occurred_at', [$from, $to]);
        $periodMovements = (clone $movementQuery)->with('product')->get();

        $paidStatuses = ['paid', 'completed'];
        $stockIn = $periodMovements->where('type', 'in')->sum('quantity');
        $stockOut = $periodMovements->where('type', 'out')->sum('quantity');
        $paidRevenue = (clone $billingQuery)->whereIn('payment_status', $paidStatuses)->sum('amount');
        $posRevenue = (clone $posSaleQuery)->sum('total');
        $pendingRevenue = (clone $billingQuery)->where('payment_status', 'pending')->sum('amount');
        $restockExpense = (clone $expenseQuery)->where('type', 'stock')->sum('amount');
        $operationalExpense = (clone $expenseQuery)->where('type', 'operational')->sum('amount');
        $totalExpense = $restockExpense + $operationalExpense;
        $costOfGoodsSold = $periodMovements
            ->where('type', 'out')
            ->sum(fn (StockMovement $movement): int => $movement->quantity * (int) ($movement->product?->buy_price ?? 0));
        $totalRevenue = $paidRevenue + $posRevenue;
        $grossProfit = $totalRevenue - $costOfGoodsSold;
        $netProfitEstimate = $grossProfit - $totalExpense;

        $inventoryValue = $products->sum(fn (Product $product): int => $product->stock * $product->buy_price);
        $retailValue = $products->sum(fn (Product $product): int => $product->stock * $product->sell_price);
        $lowStockProducts = $products
            ->filter(fn (Product $product): bool => $product->stock <= 0 || ($product->min_stock > 0 && $product->stock <= $product->min_stock))
            ->values();
        $cashierRows = (clone $posSaleQuery)
            ->select('user_id')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(total), 0) as net_sales')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN total ELSE 0 END), 0) as qris_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_total")
            ->groupBy('user_id')
            ->orderByDesc('net_sales')
            ->get();
        $users = User::query()
            ->whereIn('id', $cashierRows->pluck('user_id'))
            ->get()
            ->keyBy('id');

        return [
            'store' => StoreSetting::current(),
            'activeBranch' => $activeBranch,
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'summary' => [
                'products' => $products->count(),
                'stock_in' => $stockIn,
                'stock_out' => $stockOut,
                'low_stock' => $lowStockProducts->count(),
                'out_of_stock' => $products->where('stock', '<=', 0)->count(),
                'inventory_value' => $inventoryValue,
                'retail_value' => $retailValue,
                'billings' => (clone $billingQuery)->count(),
                'paid_revenue' => $paidRevenue,
                'pos_revenue' => $posRevenue,
                'total_revenue' => $totalRevenue,
                'pending_revenue' => $pendingRevenue,
                'cost_of_goods_sold' => $costOfGoodsSold,
                'restock_expense' => $restockExpense,
                'operational_expense' => $operationalExpense,
                'total_expense' => $totalExpense,
                'gross_profit' => $grossProfit,
                'net_profit_estimate' => $netProfitEstimate,
            ],
            'topProducts' => $products->sortByDesc('stock')->take(8)->values(),
            'lowStockProducts' => $lowStockProducts->take(8)->values(),
            'cashierRevenues' => $cashierRows
                ->map(fn ($row): array => [
                    'cashier' => $users->get($row->user_id)?->name ?? 'Kasir',
                    'email' => $users->get($row->user_id)?->email,
                    'sales_count' => (int) $row->sales_count,
                    'gross_sales' => (int) $row->gross_sales,
                    'discount_total' => (int) $row->discount_total,
                    'net_sales' => (int) $row->net_sales,
                    'cash_total' => (int) $row->cash_total,
                    'qris_total' => (int) $row->qris_total,
                    'card_total' => (int) $row->card_total,
                ])
                ->values(),
        ];
    }
}
