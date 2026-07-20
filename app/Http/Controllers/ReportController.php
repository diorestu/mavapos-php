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

    public function downloadExcel(Request $request): Response
    {
        $data = $this->reportData($request);
        $fileName = sprintf(
            'laporan-%s-sampai-%s.xls',
            $data['period']['from']->format('Y-m-d'),
            $data['period']['to']->format('Y-m-d'),
        );

        return response()->view('pages.reports.excel', [
            'title' => 'Laporan MavaPOS',
            ...$data,
        ], 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function journal(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $from = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : now()->startOfMonth();
        $to = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : now()->endOfDay();
        $branch = app(BranchContext::class)->active();

        $lines = collect();
        PosSale::query()->active()->with(['items.product'])->where('branch_id', $branch->id)
            ->whereBetween('sold_at', [$from, $to])->orderBy('sold_at')->get()
            ->each(function (PosSale $sale) use ($lines): void {
                $paymentAccount = ['cash' => 'Kas', 'qris' => 'Bank / QRIS', 'card' => 'Bank / Kartu'][$sale->payment_method] ?? 'Kas';
                $lines->push($this->journalLine($sale->sold_at, $sale->invoice_number, 'Penjualan POS', $paymentAccount, 'Penjualan', $sale->total));
                $cost = $sale->items->sum(fn ($item): int => $item->quantity * (int) ($item->product?->buy_price ?? 0));
                if ($cost > 0) {
                    $lines->push($this->journalLine($sale->sold_at, $sale->invoice_number, 'Pengakuan HPP', 'Beban Pokok Penjualan', 'Persediaan', $cost));
                }
            });
        Expense::query()->where('branch_id', $branch->id)->whereBetween('spent_at', [$from, $to])->orderBy('spent_at')->get()
            ->each(function (Expense $expense) use ($lines): void {
                $debit = $expense->type === 'stock' ? 'Persediaan' : 'Beban Operasional';
                $lines->push($this->journalLine($expense->spent_at, $expense->expense_number, $expense->title, $debit, 'Kas', $expense->amount));
            });

        $totalDebit = $lines->sum('debit');

        return view('pages.reports.journal', [
            'title' => 'Jurnal Transaksi',
            'activeBranch' => $branch,
            'lines' => $lines->sortBy('date')->values(),
            'totalDebit' => $totalDebit,
            'totalCredit' => $lines->sum('credit'),
            'filters' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
        ]);
    }

    public function downloadFinancial(Request $request): Response
    {
        $data = $this->financialData($request);
        return Pdf::loadView('pages.reports.financial-pdf', $data)->setPaper('a4')->download(
            'laporan-keuangan-'.$data['filters']['date_from'].'-sampai-'.$data['filters']['date_to'].'.pdf'
        );
    }

    public function downloadProfitLoss(Request $request): Response
    {
        $data = $this->financialData($request);
        return Pdf::loadView('pages.reports.profit-loss-pdf', $data)->setPaper('a4')->download(
            'laporan-laba-rugi-'.$data['filters']['date_from'].'-sampai-'.$data['filters']['date_to'].'.pdf'
        );
    }

    private function financialData(Request $request): array
    {
        $validated = $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        $from = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : now()->startOfMonth();
        $to = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : now()->endOfDay();
        $branch = app(BranchContext::class)->active();
        $sales = PosSale::query()->active()->with(['items.product'])->where('branch_id', $branch->id)->whereBetween('sold_at', [$from, $to])->orderBy('sold_at')->get();
        $expenses = Expense::query()->where('branch_id', $branch->id)->whereBetween('spent_at', [$from, $to])->orderBy('spent_at')->get();
        $revenue = (int) $sales->sum('total');
        $cogs = (int) $sales->sum(fn (PosSale $sale): int => $sale->items->sum(fn ($item): int => $item->quantity * (int) ($item->product?->buy_price ?? 0)));
        $expenseTotal = (int) $expenses->sum('amount');
        return ['store' => StoreSetting::current(), 'activeBranch' => $branch, 'sales' => $sales, 'expenses' => $expenses, 'journalLines' => $this->journalLines($sales, $expenses), 'filters' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()], 'summary' => compact('revenue', 'cogs', 'expenseTotal') + ['grossProfit' => $revenue - $cogs, 'netProfit' => $revenue - $cogs - $expenseTotal]];
    }

    private function journalLines($sales, $expenses): array
    {
        $lines = [];
        foreach ($sales as $sale) {
            $account = ['cash' => 'Kas', 'qris' => 'Bank / QRIS', 'card' => 'Bank / Kartu'][$sale->payment_method] ?? 'Kas';
            $lines[] = $this->journalLine($sale->sold_at, $sale->invoice_number, 'Penjualan POS', $account, 'Penjualan', $sale->total);
        }
        foreach ($expenses as $expense) {
            $lines[] = $this->journalLine($expense->spent_at, $expense->expense_number, $expense->title, $expense->type === 'stock' ? 'Persediaan' : 'Beban Operasional', 'Kas', $expense->amount);
        }
        return $lines;
    }

    private function journalLine(Carbon $date, string $reference, string $description, string $debitAccount, string $creditAccount, int $amount): array
    {
        return compact('date', 'reference', 'description', 'debitAccount', 'creditAccount', 'amount')
            + ['debit' => $amount, 'credit' => $amount];
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
        $posSaleQuery = PosSale::query()->active()
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
