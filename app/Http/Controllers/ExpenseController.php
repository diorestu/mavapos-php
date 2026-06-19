<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(): View
    {
        $expenses = Expense::query()
            ->with('product')
            ->latest('spent_at')
            ->latest()
            ->get();

        return view('pages.expenses.index', [
            'title' => 'Pengeluaran',
            'expenses' => $expenses,
            'products' => Product::query()->orderBy('name')->get(['id', 'sku', 'name', 'stock', 'buy_price']),
            'summary' => [
                'total' => $expenses->sum('amount'),
                'operational' => $expenses->where('type', 'operational')->sum('amount'),
                'stock' => $expenses->where('type', 'stock')->sum('amount'),
                'count' => $expenses->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['operational', 'stock'])],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'integer', 'min:1'],
            'product_id' => ['required_if:type,stock', 'nullable', 'integer', Rule::exists('products', 'id')],
            'quantity' => ['required_if:type,stock', 'nullable', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'spent_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($validated): void {
            $stockMovement = null;
            $product = null;

            if ($validated['type'] === 'stock') {
                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($validated['product_id']);

                $quantity = (int) $validated['quantity'];
                $stockBefore = $product->stock;
                $stockAfter = $stockBefore + $quantity;

                $product->update([
                    'stock' => $stockAfter,
                    'buy_price' => (int) ($validated['unit_cost'] ?? $product->buy_price),
                ]);

                $stockMovement = StockMovement::query()->create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'reference' => $validated['reference'] ?? $this->nextExpenseNumber(),
                    'note' => 'Pengeluaran stok: '.$validated['title'],
                    'occurred_at' => $validated['spent_at'],
                ]);
            }

            Expense::query()->create([
                'product_id' => $product?->id,
                'stock_movement_id' => $stockMovement?->id,
                'expense_number' => $this->nextExpenseNumber(),
                'type' => $validated['type'],
                'category' => $validated['category'] ?? null,
                'title' => $validated['title'],
                'amount' => (int) $validated['amount'],
                'quantity' => isset($validated['quantity']) ? (int) $validated['quantity'] : null,
                'unit_cost' => isset($validated['unit_cost']) ? (int) $validated['unit_cost'] : null,
                'reference' => $validated['reference'] ?? null,
                'note' => $validated['note'] ?? null,
                'spent_at' => $validated['spent_at'],
            ]);
        });

        return redirect()->route('expenses')->with('success', 'Pengeluaran berhasil dicatat.');
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
