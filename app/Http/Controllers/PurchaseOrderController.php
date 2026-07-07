<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        $branchId = app(BranchContext::class)->activeId();
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'product.category', 'branch', 'user', 'receiver'])
            ->where('branch_id', $branchId)
            ->latest('ordered_at')
            ->latest()
            ->get();

        return view('pages.purchase-orders.index', [
            'title' => 'Purchase Order',
            'orders' => $orders,
            'suppliers' => Supplier::query()->where('status', 'aktif')->orderBy('name')->get(),
            'products' => Product::query()->with('category')->orderBy('name')->get(),
            'summary' => [
                'draft' => $orders->where('status', 'draft')->count(),
                'received' => $orders->where('status', 'received')->count(),
                'total' => $orders->sum('total_amount'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('status', 'aktif')],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'ordered_at' => ['required', 'date'],
        ]);

        PurchaseOrder::query()->create([
            'branch_id' => app(BranchContext::class)->activeId(),
            'supplier_id' => $validated['supplier_id'],
            'product_id' => $validated['product_id'],
            'user_id' => auth()->id(),
            'po_number' => $this->nextPoNumber(),
            'status' => 'draft',
            'quantity' => (int) $validated['quantity'],
            'unit_cost' => (int) $validated['unit_cost'],
            'total_amount' => (int) $validated['quantity'] * (int) $validated['unit_cost'],
            'reference' => $validated['reference'] ?? null,
            'note' => $validated['note'] ?? null,
            'ordered_at' => $validated['ordered_at'],
        ]);

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order berhasil dibuat.');
    }

    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return back()->withErrors(['po' => 'Purchase order ini sudah diproses.']);
        }

        DB::transaction(function () use ($purchaseOrder): void {
            $purchaseOrder->refresh();
            $product = Product::query()
                ->whereKey($purchaseOrder->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            $inventory = app(BranchInventoryManager::class)->forProduct((int) $purchaseOrder->branch_id, $product, true);
            $stockBefore = $inventory->stock;
            $stockAfter = $stockBefore + $purchaseOrder->quantity;

            $product->update([
                'buy_price' => $purchaseOrder->unit_cost,
            ]);
            $inventory->update([
                'stock' => $stockAfter,
            ]);
            $product->update([
                'stock' => $stockAfter,
            ]);

            $movement = StockMovement::query()->create([
                'branch_id' => $purchaseOrder->branch_id,
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => $purchaseOrder->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference' => $purchaseOrder->po_number,
                'note' => 'Restock dari purchase order '.$purchaseOrder->supplier?->name,
                'occurred_at' => now(),
            ]);

            $expense = Expense::query()->create([
                'branch_id' => $purchaseOrder->branch_id,
                'product_id' => $product->id,
                'stock_movement_id' => $movement->id,
                'expense_number' => $this->nextExpenseNumber(),
                'type' => 'stock',
                'category' => 'Restock',
                'title' => 'Restock '.$product->name,
                'amount' => $purchaseOrder->total_amount,
                'quantity' => $purchaseOrder->quantity,
                'unit_cost' => $purchaseOrder->unit_cost,
                'reference' => $purchaseOrder->po_number,
                'note' => $purchaseOrder->note,
                'spent_at' => now(),
            ]);

            $purchaseOrder->update([
                'status' => 'received',
                'received_by' => auth()->id(),
                'expense_id' => $expense->id,
                'stock_movement_id' => $movement->id,
                'received_at' => now(),
            ]);
        });

        return redirect()->route('purchase-orders.index')->with('success', 'Restock berhasil diterima dan stok diperbarui.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return back()->withErrors(['po' => 'Hanya purchase order draft yang bisa dibatalkan.']);
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order dibatalkan.');
    }

    private function nextPoNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ymd').'-';
        $lastNumber = PurchaseOrder::query()
            ->where('po_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->latest('id')
            ->value('po_number');
        $sequence = $lastNumber ? ((int) str($lastNumber)->afterLast('-')->toString()) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function nextExpenseNumber(): string
    {
        $prefix = 'EXP-'.now()->format('Ymd').'-';
        $lastNumber = Expense::query()
            ->where('expense_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->latest('id')
            ->value('expense_number');
        $sequence = $lastNumber ? ((int) str($lastNumber)->afterLast('-')->toString()) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
