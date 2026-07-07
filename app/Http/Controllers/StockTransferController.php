<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Support\BranchInventoryManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StockTransferController extends Controller
{
    public function index(): View
    {
        return view('pages.stock-transfers.index', [
            'title' => 'Transfer Stok',
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'products' => Product::query()
                ->with('category')
                ->orderBy('name')
                ->get(),
            'transfers' => StockTransfer::query()
                ->with(['fromBranch', 'toBranch', 'product.category', 'user'])
                ->latest('transferred_at')
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_branch_id' => ['required', 'integer', 'exists:branches,id', 'different:to_branch_id'],
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $activeBranchIds = Branch::query()
            ->where('is_active', true)
            ->whereIn('id', [$validated['from_branch_id'], $validated['to_branch_id']])
            ->pluck('id')
            ->all();

        validator($validated, [
            'from_branch_id' => [Rule::in($activeBranchIds)],
            'to_branch_id' => [Rule::in($activeBranchIds)],
        ], [
            'from_branch_id.in' => 'Cabang asal harus aktif.',
            'to_branch_id.in' => 'Cabang tujuan harus aktif.',
        ])->validate();

        try {
            DB::transaction(function () use ($validated): void {
                $product = Product::query()
                    ->whereKey($validated['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $inventoryManager = app(BranchInventoryManager::class);
                $fromInventory = $inventoryManager->forProduct((int) $validated['from_branch_id'], $product, true);
                $toInventory = $inventoryManager->forProduct((int) $validated['to_branch_id'], $product, true);
                $quantity = (int) $validated['quantity'];

                if ($fromInventory->stock < $quantity) {
                    abort(422, 'Stok cabang asal tidak cukup untuk transfer.');
                }

                $fromStockBefore = $fromInventory->stock;
                $toStockBefore = $toInventory->stock;
                $fromStockAfter = $fromStockBefore - $quantity;
                $toStockAfter = $toStockBefore + $quantity;
                $transferNumber = $this->nextTransferNumber();

                $fromInventory->update(['stock' => $fromStockAfter]);
                $toInventory->update(['stock' => $toStockAfter]);
                $product->update(['stock' => $fromStockAfter]);

                StockTransfer::query()->create([
                    'transfer_number' => $transferNumber,
                    'from_branch_id' => $validated['from_branch_id'],
                    'to_branch_id' => $validated['to_branch_id'],
                    'product_id' => $product->id,
                    'user_id' => auth()->id(),
                    'quantity' => $quantity,
                    'from_stock_before' => $fromStockBefore,
                    'from_stock_after' => $fromStockAfter,
                    'to_stock_before' => $toStockBefore,
                    'to_stock_after' => $toStockAfter,
                    'note' => $validated['note'] ?? null,
                    'transferred_at' => now(),
                ]);

                StockMovement::query()->create([
                    'branch_id' => $validated['from_branch_id'],
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => $quantity,
                    'stock_before' => $fromStockBefore,
                    'stock_after' => $fromStockAfter,
                    'reference' => $transferNumber,
                    'note' => 'Transfer stok ke cabang tujuan',
                    'occurred_at' => now(),
                ]);

                StockMovement::query()->create([
                    'branch_id' => $validated['to_branch_id'],
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $quantity,
                    'stock_before' => $toStockBefore,
                    'stock_after' => $toStockAfter,
                    'reference' => $transferNumber,
                    'note' => 'Transfer stok dari cabang asal',
                    'occurred_at' => now(),
                ]);
            });
        } catch (HttpException $exception) {
            return back()->withInput()->withErrors(['quantity' => $exception->getMessage()]);
        }

        return redirect()->route('stock-transfers.index')->with('success', 'Transfer stok berhasil dicatat.');
    }

    private function nextTransferNumber(): string
    {
        return 'TRF-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
    }
}
