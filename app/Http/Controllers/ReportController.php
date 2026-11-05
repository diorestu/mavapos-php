<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\Expense;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\SaleRefund;
use App\Models\StockMovement;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\SalesReportExcelExporter;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use App\Support\LocalTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
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
        $data = $this->reportData($request, includeTransactions: false);
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

    public function downloadExcel(Request $request, SalesReportExcelExporter $exporter): Response
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $from = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'], LocalTime::TIMEZONE)->startOfDay()
            : now(LocalTime::TIMEZONE)->startOfMonth();
        $to = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'], LocalTime::TIMEZONE)->endOfDay()
            : now(LocalTime::TIMEZONE)->endOfDay();
        $branch = app(BranchContext::class)->active();
        $transactions = PosSale::query()->active()->with('payments')
            ->where('branch_id', $branch->id)
            ->whereBetween('sold_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->orderBy('sold_at')->orderBy('id')->lazy()
            ->map(fn (PosSale $sale): array => [
                'invoice_number' => $sale->invoice_number,
                'sold_at' => $sale->sold_at->copy()->timezone(LocalTime::TIMEZONE),
                'subtotal' => (int) $sale->subtotal,
                'discount' => (int) $sale->discount,
                'received_amount' => $sale->payment_method === 'online_merchant' ? (int) $sale->paid_amount : (int) $sale->total,
                'description' => $this->saleDescription($sale).' · '.$this->paymentSummary($sale),
                'payment_method' => collect($this->salePayments($sale))->pluck('method')->unique()
                    ->map(fn (string $method): string => $this->paymentLabel($method))->implode(' + ') ?: 'Gratis',
                'online_merchant' => ! empty($sale->online_merchant) || $sale->payment_method === 'online_merchant' ? 'Ya' : 'Tidak',
                'merchant_name' => PosSale::ONLINE_MERCHANTS[$sale->online_merchant] ?? ($sale->payment_method === 'online_merchant' ? 'Online Merchant' : '-'),
                'merchant_deduction' => (int) $sale->online_merchant_deduction,
            ]);
        $fileName = sprintf(
            'laporan-%s-sampai-%s.xlsx',
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        return $exporter->download($transactions, $fileName, $branch->name, $this->refundQuery($branch->id, $from, $to)->orderBy('refunded_at')->lazy());
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
        PosSale::query()->active()->with(['items.product', 'payments'])->where('branch_id', $branch->id)
            ->whereBetween('sold_at', [$from, $to])->orderBy('sold_at')->get()
            ->each(function (PosSale $sale) use ($lines): void {
                $lines->push(...$this->saleJournalLines($sale));
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

        $this->refundQuery($branch->id, $from, $to)->get()->each(function (SaleRefund $refund) use ($lines): void {
            $lines->push(...$this->refundJournalLines($refund));
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
        $sales = PosSale::query()->active()->with(['items.product', 'payments'])->where('branch_id', $branch->id)->whereBetween('sold_at', [$from, $to])->orderBy('sold_at')->get();
        $expenses = Expense::query()->where('branch_id', $branch->id)->whereBetween('spent_at', [$from, $to])->orderBy('spent_at')->get();
        $refunds = $this->refundQuery($branch->id, $from, $to)->get();
        $refundTotal = (int) $refunds->sum('amount');
        $refundCostRecovery = (int) $refunds->sum(fn (SaleRefund $refund): int => $this->refundCostRecovery($refund));
        $revenue = (int) $sales->sum('total');
        $cogs = (int) $sales->sum(fn (PosSale $sale): int => $sale->items->sum(fn ($item): int => $item->quantity * (int) ($item->product?->buy_price ?? 0)));
        $cogs -= $refundCostRecovery;
        $merchantDeduction = (int) $sales->sum('online_merchant_deduction');
        $expenseTotal = (int) $expenses->sum('amount');

        $journalLines = $this->journalLines($sales, $expenses);
        foreach ($refunds as $refund) {
            array_push($journalLines, ...$this->refundJournalLines($refund));
        }

        return ['store' => StoreSetting::current(), 'activeBranch' => $branch, 'sales' => $sales, 'expenses' => $expenses, 'refunds' => $refunds, 'journalLines' => $this->summarizeJournalLines($journalLines), 'filters' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()], 'summary' => compact('revenue', 'cogs', 'expenseTotal', 'merchantDeduction') + [
            'refund_total' => $refundTotal,
            'refund_cost_recovery' => $refundCostRecovery,
            'net_after_refunds' => $revenue - $merchantDeduction - $refundTotal,
            'grossProfit' => $revenue - $refundTotal - $cogs,
            'netProfit' => $revenue - $refundTotal - $cogs - $expenseTotal - $merchantDeduction,
        ]];
    }

    private function journalLines($sales, $expenses): array
    {
        $lines = [];
        foreach ($sales as $sale) {
            array_push($lines, ...$this->saleJournalLines($sale));
        }
        foreach ($expenses as $expense) {
            $lines[] = $this->journalLine($expense->spent_at, $expense->expense_number, $expense->title, $expense->type === 'stock' ? 'Persediaan' : 'Beban Operasional', 'Kas', $expense->amount);
        }

        return $lines;
    }

    private function saleJournalLines(PosSale $sale): array
    {
        $lines = [];
        $isMerchant = ! empty($sale->online_merchant) || $sale->payment_method === 'online_merchant';
        $merchantLabel = PosSale::ONLINE_MERCHANTS[$sale->online_merchant] ?? 'Online Merchant';
        $revenueAccount = $isMerchant ? 'Pendapatan Online Merchant' : 'Penjualan';
        if ($sale->payment_method === 'online_merchant') {
            $description = 'Pendapatan Online Merchant — '.$merchantLabel;
            $lines[] = $this->journalLine($sale->sold_at, $sale->invoice_number, 'Penerimaan bersih Online Merchant — '.$merchantLabel, 'Penerimaan Online Merchant', '', $sale->paid_amount);
            if ($sale->online_merchant_deduction > 0) {
                $lines[] = $this->journalLine($sale->sold_at, $sale->invoice_number, 'Potongan Online Merchant — '.$merchantLabel, 'Potongan Online Merchant', '', $sale->online_merchant_deduction);
            }
            if ($sale->discount > 0) {
                $lines[] = $this->journalLine($sale->sold_at, $sale->invoice_number, $isMerchant ? 'Diskon penjualan Online Merchant — '.$merchantLabel : $description, 'Diskon Penjualan', '', $sale->discount);
            }
            $lines[] = $this->journalLine($sale->sold_at, $sale->invoice_number, $description, '', $revenueAccount, $sale->subtotal);

            return $lines;
        }
        $hasDiscount = $sale->discount > 0;
        $description = $this->journalSaleDescription($sale);

        foreach ($this->salePayments($sale) as $payment) {
            if ($hasDiscount && $payment['amount'] === 0) {
                continue;
            }

            $account = ['cash' => 'Kas', 'qris' => 'Bank / QRIS', 'card' => 'Bank / Kartu'][$payment['method']] ?? 'Kas';
            $lines[] = $this->journalLine($sale->sold_at, $sale->invoice_number, $description, $account, $hasDiscount ? '' : $revenueAccount, $payment['amount']);
        }

        if ($hasDiscount) {
            $lines[] = $this->journalLine($sale->sold_at, $sale->invoice_number, $isMerchant ? 'Diskon penjualan Online Merchant — '.$merchantLabel : $description, 'Diskon Penjualan', '', $sale->discount);
            $lines[] = $this->journalLine($sale->sold_at, $sale->invoice_number, $description, '', $revenueAccount, $sale->subtotal);
        }

        return $lines;
    }

    private function summarizeJournalLines(array $lines): array
    {
        return collect($lines)
            ->groupBy(fn (array $line): string => implode('|', [
                $line['date']->toDateString(),
                $line['description'],
                $line['debitAccount'],
                $line['creditAccount'],
            ]))
            ->map(function ($group): array {
                $first = $group->first();
                $amount = (int) $group->sum('amount');

                return [
                    ...$first,
                    'date' => $first['date']->copy()->startOfDay(),
                    'reference' => 'Ringkasan',
                    'amount' => $amount,
                    'debit' => (int) $group->sum('debit'),
                    'credit' => (int) $group->sum('credit'),
                ];
            })
            ->sortBy('date')
            ->values()
            ->all();
    }

    private function journalLine(Carbon $date, string $reference, string $description, string $debitAccount, string $creditAccount, int $amount): array
    {
        return compact('date', 'reference', 'description', 'debitAccount', 'creditAccount', 'amount')
            + ['debit' => $debitAccount !== '' ? $amount : 0, 'credit' => $creditAccount !== '' ? $amount : 0];
    }

    private function reportData(Request $request, bool $includeTransactions = true): array
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
        $merchantDeduction = (int) (clone $posSaleQuery)->where('payment_method', 'online_merchant')->selectRaw('COALESCE(SUM(total - paid_amount), 0) as deduction')->value('deduction');
        $totalExpense = $restockExpense + $operationalExpense + $merchantDeduction;
        $costOfGoodsSold = $periodMovements
            ->where('type', 'out')
            ->sum(fn (StockMovement $movement): int => $movement->quantity * (int) ($movement->product?->buy_price ?? 0));
        $refunds = $this->refundQuery($activeBranch->id, $from, $to)->orderByDesc('refunded_at')->get();
        $refundTotal = (int) $refunds->sum('amount');
        $refundCostRecovery = (int) $refunds->sum(fn (SaleRefund $refund): int => $this->refundCostRecovery($refund));
        $costOfGoodsSold -= $refundCostRecovery;
        $totalRevenue = $paidRevenue + $posRevenue;
        $grossProfit = $totalRevenue - $refundTotal - $costOfGoodsSold;
        $netProfitEstimate = $grossProfit - $totalExpense;

        $inventoryValue = $products->sum(fn (Product $product): int => $product->stock * $product->buy_price);
        $retailValue = $products->sum(fn (Product $product): int => $product->stock * $product->sell_price);
        $lowStockProducts = $products
            ->filter(fn (Product $product): bool => $product->stock <= 0 || ($product->min_stock > 0 && $product->stock <= $product->min_stock))
            ->values();
        $cashierSales = (clone $posSaleQuery)->with('payments')->get();
        $cashierRows = $cashierSales->groupBy('user_id')->map(function ($sales, $userId) {
            $payments = $sales->reduce(function (array $totals, PosSale $sale): array {
                foreach ($this->salePayments($sale) as $payment) {
                    $totals[$payment['method']] += $payment['amount'];
                }

                return $totals;
            }, ['cash' => 0, 'qris' => 0, 'card' => 0, 'online_merchant' => 0]);

            return (object) ['user_id' => $userId, 'sales_count' => $sales->count(), 'gross_sales' => $sales->sum('subtotal'), 'discount_total' => $sales->sum('discount'), 'net_sales' => $sales->sum('total'), 'cash_total' => $payments['cash'], 'qris_total' => $payments['qris'], 'card_total' => $payments['card'], 'online_merchant_total' => $payments['online_merchant']];
        })->sortByDesc('net_sales')->values();
        $dailyCashierRows = $cashierSales
            ->groupBy(fn (PosSale $sale): string => $sale->sold_at->timezone('Asia/Makassar')->toDateString())
            ->sortKeysDesc()
            ->flatMap(fn ($sales, string $date) => $sales->groupBy('user_id')->map(function ($userSales, $userId) use ($date): object {
                $payments = $userSales->reduce(function (array $totals, PosSale $sale): array {
                    foreach ($this->salePayments($sale) as $payment) {
                        $totals[$payment['method']] += $payment['amount'];
                    }

                    return $totals;
                }, ['cash' => 0, 'qris' => 0, 'card' => 0, 'online_merchant' => 0]);

                return (object) [
                    'date' => $date,
                    'user_id' => $userId,
                    'sales_count' => $userSales->count(),
                    'net_sales' => $userSales->sum('total'),
                    'cash_total' => $payments['cash'],
                    'qris_total' => $payments['qris'],
                    'card_total' => $payments['card'], 'online_merchant_total' => $payments['online_merchant'],
                ];
            })->values())
            ->sortByDesc(fn (object $row): string => $row->date)
            ->values();
        $users = User::query()
            ->whereIn('id', $cashierRows->pluck('user_id'))
            ->get()
            ->keyBy('id');
        $transactions = $includeTransactions
            ? (clone $posSaleQuery)
                ->with(['user', 'payments'])
                ->latest('sold_at')
                ->get()
                ->map(fn (PosSale $sale): array => [
                    'sold_at' => $sale->sold_at,
                    'invoice_number' => $sale->invoice_number,
                    'cashier' => $sale->user?->name ?? 'Kasir',
                    'description' => $this->saleDescription($sale),
                    'payment_summary' => $this->paymentSummary($sale),
                    'total' => (int) $sale->total,
                ])
            : collect();

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
                'refund_total' => $refundTotal,
                'refund_cost_recovery' => $refundCostRecovery,
                'net_after_refunds' => $totalRevenue - $merchantDeduction - $refundTotal,
                'pending_revenue' => $pendingRevenue,
                'cost_of_goods_sold' => $costOfGoodsSold,
                'restock_expense' => $restockExpense,
                'operational_expense' => $operationalExpense,
                'online_merchant_deduction' => $merchantDeduction,
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
                    'online_merchant_total' => (int) $row->online_merchant_total,
                ])
                ->values(),
            'dailyCashierRevenues' => $dailyCashierRows
                ->map(fn (object $row): array => [
                    'date' => $row->date,
                    'cashier' => $users->get($row->user_id)?->name ?? 'Kasir',
                    'sales_count' => (int) $row->sales_count,
                    'net_sales' => (int) $row->net_sales,
                    'cash_total' => (int) $row->cash_total,
                    'qris_total' => (int) $row->qris_total,
                    'card_total' => (int) $row->card_total,
                    'online_merchant_total' => (int) $row->online_merchant_total,
                ])
                ->values(),
            'transactions' => $transactions,
            'refunds' => $refunds,
        ];
    }

    private function refundQuery(int $branchId, Carbon $from, Carbon $to): Builder
    {
        return SaleRefund::query()->with(['sale', 'items.saleItem.product'])
            ->where('tenant_id', auth()->user()->tenantOwnerId())
            ->where('branch_id', $branchId)
            ->whereBetween('refunded_at', [
                Carbon::parse($from->toDateString(), LocalTime::TIMEZONE)->startOfDay()->utc(),
                Carbon::parse($to->toDateString(), LocalTime::TIMEZONE)->endOfDay()->utc(),
            ]);
    }

    private function refundCostRecovery(SaleRefund $refund): int
    {
        return (int) $refund->items->where('restock', true)->sum(
            fn ($item): int => $item->quantity * (int) ($item->saleItem?->product?->buy_price ?? 0)
        );
    }

    private function refundJournalLines(SaleRefund $refund): array
    {
        $account = ['cash' => 'Kas', 'qris' => 'Bank / QRIS', 'card' => 'Bank / Kartu', 'bank_transfer' => 'Bank'][$refund->refund_method] ?? 'Bank';
        $date = $refund->refunded_at->copy()->timezone(LocalTime::TIMEZONE);
        $lines = [$this->journalLine($date, $refund->refund_number, 'Retur '.$refund->sale?->invoice_number.' — '.$refund->reason, 'Retur Penjualan', $account, $refund->amount)];
        $costRecovery = $this->refundCostRecovery($refund);
        if ($costRecovery > 0) {
            $lines[] = $this->journalLine($date, $refund->refund_number, 'Pemulihan persediaan retur', 'Persediaan', 'Beban Pokok Penjualan', $costRecovery);
        }

        return $lines;
    }

    private function salePayments(PosSale $sale): array
    {
        if ($sale->payment_method === 'online_merchant') {
            return [['method' => 'online_merchant', 'amount' => $sale->paid_amount]];
        }

        if ($sale->payments->isNotEmpty()) {
            return $sale->payments->map(fn ($payment): array => ['method' => $payment->payment_method, 'amount' => $payment->amount])->all();
        }

        return in_array($sale->payment_method, ['cash', 'qris', 'card'], true)
            ? [['method' => $sale->payment_method, 'amount' => $sale->total]]
            : [];
    }

    private function saleDescription(PosSale $sale): string
    {
        if ($sale->payment_method === 'online_merchant') {
            return (PosSale::ONLINE_MERCHANTS[$sale->online_merchant] ?? 'Online Merchant').' · Potongan Online Merchant Rp'.number_format($sale->online_merchant_deduction, 0, ',', '.');
        }

        if ($sale->payment_method === 'free') {
            $category = ['owner' => 'Owner', 'influencer' => 'Influencer', 'partnership' => 'Partnership'][$sale->complimentary_category] ?? 'Lainnya';

            return 'Gratis — '.$category.($sale->complimentary_recipient_name ? ': '.$sale->complimentary_recipient_name : '');
        }

        if ((int) $sale->discount > 0) {
            return $sale->loyalty_reward === 'fifty_percent'
                ? 'Diskon Kartu Loyalitas 50%'
                : 'Diskon Rp'.number_format((int) $sale->discount, 0, ',', '.');
        }

        return 'Penjualan reguler';
    }

    private function paymentSummary(PosSale $sale): string
    {
        $payments = $this->salePayments($sale);

        if ($payments === []) {
            return 'Gratis';
        }

        return collect($payments)
            ->map(fn (array $payment): string => $this->paymentLabel($payment['method']).' Rp'.number_format((int) $payment['amount'], 0, ',', '.'))
            ->implode(' + ');
    }

    private function journalSaleDescription(PosSale $sale): string
    {
        if ($sale->online_merchant) {
            return 'Pendapatan Online Merchant — '.(PosSale::ONLINE_MERCHANTS[$sale->online_merchant] ?? 'Online Merchant');
        }

        $description = $this->saleDescription($sale);

        return $sale->payment_method === 'split'
            ? 'Penjualan POS — Split Payment: '.$this->paymentSummary($sale)
            : 'Penjualan POS — '.$description;
    }

    private function paymentLabel(string $method): string
    {
        return ['cash' => 'Tunai', 'qris' => 'QRIS', 'card' => 'Kartu', 'online_merchant' => 'Online Merchant'][$method] ?? ucfirst($method);
    }
}
