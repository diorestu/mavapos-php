<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchRawMaterialInventory;
use App\Models\Product;
use App\Models\RawMaterial;
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
            'rawMaterials' => RawMaterial::query()
                ->orderBy('name')
                ->get(),
            'transfers' => StockTransfer::query()
                ->with(['fromBranch', 'toBranch', 'product.category', 'rawMaterial', 'user'])
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
            'stock_item' => ['nullable', 'string', 'regex:/^(product|raw-material)-[0-9]+$/', 'required_without:product_id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'required_without:stock_item'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $validated['stock_item'] = $validated['stock_item'] ?? 'product-'.$validated['product_id'];

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
                if (Str::startsWith($validated['stock_item'], 'raw-material-')) {
                    $this->storeRawMaterialTransfer($validated);

                    return;
                }

                $this->storeProductTransfer($validated);
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

    private function storeProductTransfer(array $validated): void
    {
        $productId = Str::startsWith($validated['stock_item'], 'product-')
            ? (int) Str::after($validated['stock_item'], 'product-')
            : (int) $validated['product_id'];
        $product = Product::query()
            ->whereKey($productId)
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
            'raw_material_id' => null,
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
    }

    private function storeRawMaterialTransfer(array $validated): void
    {
        $rawMaterialId = (int) Str::after($validated['stock_item'], 'raw-material-');
        $rawMaterial = RawMaterial::query()
            ->whereKey($rawMaterialId)
            ->lockForUpdate()
            ->firstOrFail();
        $fromInventory = $this->rawMaterialInventory((int) $validated['from_branch_id'], $rawMaterial, true);
        $toInventory = $this->rawMaterialInventory((int) $validated['to_branch_id'], $rawMaterial, true);
        $quantity = (int) $validated['quantity'];

        if ((float) $fromInventory->stock < $quantity) {
            abort(422, 'Stok cabang asal tidak cukup untuk transfer.');
        }

        $fromStockBefore = (float) $fromInventory->stock;
        $toStockBefore = (float) $toInventory->stock;
        $fromStockAfter = $fromStockBefore - $quantity;
        $toStockAfter = $toStockBefore + $quantity;
        $transferNumber = $this->nextTransferNumber();

        $fromInventory->update(['stock' => $fromStockAfter]);
        $toInventory->update(['stock' => $toStockAfter]);
        $rawMaterial->update(['stock' => $fromStockAfter]);

        StockTransfer::query()->create([
            'transfer_number' => $transferNumber,
            'from_branch_id' => $validated['from_branch_id'],
            'to_branch_id' => $validated['to_branch_id'],
            'product_id' => null,
            'raw_material_id' => $rawMaterial->id,
            'user_id' => auth()->id(),
            'quantity' => $quantity,
            'from_stock_before' => $fromStockBefore,
            'from_stock_after' => $fromStockAfter,
            'to_stock_before' => $toStockBefore,
            'to_stock_after' => $toStockAfter,
            'note' => $validated['note'] ?? null,
            'transferred_at' => now(),
        ]);
    }

    private function rawMaterialInventory(int $branchId, RawMaterial $rawMaterial, bool $lock = false): BranchRawMaterialInventory
    {
        $query = BranchRawMaterialInventory::query()
            ->where('branch_id', $branchId)
            ->where('raw_material_id', $rawMaterial->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first()
            ?? BranchRawMaterialInventory::query()->create([
                'branch_id' => $branchId,
                'raw_material_id' => $rawMaterial->id,
                'stock' => $this->defaultRawMaterialStockForBranch($branchId, (float) $rawMaterial->stock),
                'min_stock' => (float) $rawMaterial->min_stock,
            ]);
    }

    private function defaultRawMaterialStockForBranch(int $branchId, float $legacyStock): float
    {
        $defaultBranchId = Branch::query()->orderBy('id')->value('id');

        return $defaultBranchId === $branchId ? $legacyStock : 0;
    }
}
