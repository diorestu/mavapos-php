<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipeItem;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CashierShiftSummaryService;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $branchId = app(BranchContext::class)->activeId();
        $products = Product::query()
            ->with(['category', 'variants'])
            ->orderBy('name')
            ->get();
        $openShift = CashierShift::query()
            ->with(['user', 'branch'])
            ->where('branch_id', $branchId)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
        $activeShift = $openShift?->user_id === auth()->id() ? $openShift : null;
        $blockingShift = $openShift && $openShift->user_id !== auth()->id() ? $openShift : null;
        $lastClosedShift = CashierShift::query()
            ->with(['user', 'branch'])
            ->where('branch_id', $branchId)
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->first();

        $activeShiftPayload = $activeShift ? $this->shiftPayload($activeShift) : null;
        $blockingShiftPayload = $blockingShift ? $this->shiftPayload($blockingShift) : null;
        $lastClosedShiftPayload = $lastClosedShift ? app(CashierShiftSummaryService::class)->recap($lastClosedShift) : null;
        $categoriesPayload = ProductCategory::query()
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category): array => [
                'code' => $category->code,
                'name' => $category->name,
            ])
            ->values();
        $itemsPayload = $products
            ->map(function (Product $product) use ($branchId): array {
                $payload = $this->productPayload($product, $branchId);
                $payload['variants'] = $product->variants
                    ->where('is_active', true)
                    ->map(fn (ProductVariant $variant): array => $this->variantPayload($product, $variant, $branchId))
                    ->values()
                    ->all();

                return $payload;
            })
            ->values();

        if ($request->wantsJson()) {
            return response()->json([
                'activeShift' => $activeShiftPayload,
                'blockingShift' => $blockingShiftPayload,
                'lastClosedShift' => $lastClosedShiftPayload,
                'categories' => $categoriesPayload,
                'items' => $itemsPayload,
            ]);
        }

        return view('pages.pos.index', [
            'title' => 'Kasir',
            'activeShift' => $activeShiftPayload,
            'blockingShift' => $blockingShiftPayload,
            'lastClosedShift' => $lastClosedShiftPayload,
            'categories' => $categoriesPayload,
            'items' => $itemsPayload,
            'cashierSopHtml' => StoreSetting::current()->cashier_sop_html,
            'availableStaff' => User::query()->where('tenant_owner_id', auth()->user()->tenantOwnerId())->whereKeyNot(auth()->id())->whereIn('role', ['owner', 'admin', 'kasir', 'gudang'])->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function startShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opening_cash_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'validated_cash_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'validated_card_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'opening_note' => ['nullable', 'string', 'max:1000'],
            'companion_staff_ids' => ['nullable', 'array', 'max:20'],
            'companion_staff_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
        $ownerId = auth()->user()->tenantOwnerId();
        $companionIds = User::query()->where('tenant_owner_id', $ownerId)->whereKeyNot(auth()->id())->whereIn('id', $validated['companion_staff_ids'] ?? [])->pluck('id')->values()->all();
        $branchId = app(BranchContext::class)->activeId();

        $shift = DB::transaction(function () use ($validated, $branchId, $companionIds): CashierShift {
            $openShift = CashierShift::query()
                ->where('branch_id', $branchId)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if ($openShift && $openShift->user_id !== auth()->id()) {
                abort(409, 'Sesi '.$openShift->user?->name.' masih aktif. Akhiri sesi tersebut sebelum kasir berikutnya mulai.');
            }

            if ($openShift) {
                return $openShift->load(['user', 'branch']);
            }

            $previousShift = CashierShift::query()
                ->where('branch_id', $branchId)
                ->whereNotNull('closed_at')
                ->lockForUpdate()
                ->latest('closed_at')
                ->first();

            $openingCashAmount = (int) ($validated['opening_cash_amount'] ?? 0);
            $validatedCashAmount = $validated['validated_cash_amount'] ?? null;
            $validatedCardAmount = $validated['validated_card_amount'] ?? null;
            $handoverValidatedAt = null;

            if ($previousShift) {
                $previousShift = app(CashierShiftSummaryService::class)->refresh($previousShift);
                $expectedCash = $previousShift->opening_cash_amount + $previousShift->cash_total;
                $expectedCard = $previousShift->card_total;

                if ($validatedCashAmount === null || (int) $validatedCashAmount !== $expectedCash) {
                    abort(422, 'Validasi cash tidak sesuai rekap sebelumnya. Nominal wajib '.number_format($expectedCash, 0, ',', '.').'.');
                }

                if ($validatedCardAmount === null || (int) $validatedCardAmount !== $expectedCard) {
                    abort(422, 'Validasi kartu tidak sesuai rekap sebelumnya. Nominal wajib '.number_format($expectedCard, 0, ',', '.').'.');
                }

                $openingCashAmount = $expectedCash;
                $handoverValidatedAt = now();
            }

            return CashierShift::query()->create([
                'user_id' => auth()->id(),
                'branch_id' => $branchId,
                'previous_cashier_shift_id' => $previousShift?->id,
                'opened_at' => now(),
                'opening_cash_amount' => $openingCashAmount,
                'validated_cash_amount' => $validatedCashAmount,
                'validated_card_amount' => $validatedCardAmount,
                'handover_validated_at' => $handoverValidatedAt,
                'opening_note' => $validated['opening_note'] ?? null,
                'companion_staff_ids' => $companionIds,
            ])->load(['user', 'branch']);
        });

        return response()->json([
            'message' => 'Shift kasir dimulai.',
            'shift' => $this->shiftPayload($shift),
        ]);
    }

    public function closeShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $branchId = app(BranchContext::class)->activeId();

        $shift = DB::transaction(function () use ($validated, $branchId): CashierShift {
            $shift = CashierShift::query()
                ->where('user_id', auth()->id())
                ->where('branch_id', $branchId)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (! $shift) {
                abort(422, 'Tidak ada shift kasir aktif untuk ditutup.');
            }

            $shift->update([
                'closed_at' => now(),
                'closing_note' => $validated['closing_note'] ?? null,
            ]);

            return app(CashierShiftSummaryService::class)->refresh($shift)->load(['user', 'branch']);
        });

        return response()->json([
            'message' => 'Shift kasir ditutup.',
            'shift' => $this->shiftPayload($shift),
            'recap' => app(CashierShiftSummaryService::class)->recap($shift),
        ]);
    }

    public function checkout(Request $request): JsonResponse
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
        ]);
        $branchId = app(BranchContext::class)->activeId();

        $sale = DB::transaction(function () use ($validated, $branchId): PosSale {
            $shift = CashierShift::query()
                ->where('user_id', auth()->id())
                ->where('branch_id', $branchId)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (! $shift) {
                abort(422, 'Mulai shift kasir sebelum menyelesaikan transaksi.');
            }

            $saleItems = [];
            $subtotal = 0;

            foreach ($validated['items'] as $line) {
                $quantity = (int) $line['quantity'];
                $sellable = $this->lockSellable($line['id'], $branchId);
                if ($sellable['stock_mode'] === 'inventory') {
                    $availableStock = (int) $sellable['inventory']->stock;
                    if ($availableStock < $quantity) {
                        abort(422, 'Stok '.$sellable['name'].' tidak cukup.');
                    }
                } elseif (! ProductRecipeItem::query()->where('product_id', $sellable['product_id'])->exists()) {
                    abort(422, 'Resep '.$sellable['name'].' belum diatur.');
                }

                $lineTotal = $sellable['price'] * $quantity;
                $subtotal += $lineTotal;

                $saleItems[] = [
                    'sellable' => $sellable,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $isComplimentary = $validated['payment_method'] === 'free';
            $discount = $isComplimentary ? $subtotal : min((int) ($validated['discount'] ?? 0), $subtotal);
            $total = $subtotal - $discount;
            $paidAmount = $validated['payment_method'] === 'cash'
                ? (int) ($validated['paid_amount'] ?? 0)
                : $total;

            if ($validated['payment_method'] === 'cash' && $paidAmount < $total) {
                abort(422, 'Uang diterima kurang dari total transaksi.');
            }

            $sale = PosSale::query()->create([
                'cashier_shift_id' => $shift->id,
                'branch_id' => $branchId,
                'user_id' => auth()->id(),
                'invoice_number' => $this->nextInvoiceNumber(),
                'payment_method' => $validated['payment_method'],
                'complimentary_category' => $isComplimentary ? $validated['complimentary_category'] : null,
                'complimentary_recipient_name' => $isComplimentary ? $validated['complimentary_recipient_name'] : null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'change_amount' => max(0, $paidAmount - $total),
                'sold_at' => now(),
            ]);

            foreach ($saleItems as $line) {
                $sellable = $line['sellable'];
                $quantity = $line['quantity'];
                $inventory = $sellable['inventory'];
                $stockBefore = $inventory ? (int) $inventory->stock : 0;

                if ($sellable['stock_mode'] === 'inventory') {
                    $inventory->update(['stock' => $stockBefore - $quantity]);
                    $sellable['model']->update(['stock' => $inventory->stock]);
                }

                $sale->items()->create([
                    'product_id' => $sellable['product_id'],
                    'product_variant_id' => $sellable['variant_id'],
                    'item_type' => $sellable['type'],
                    'name' => $sellable['name'],
                    'sku' => $sellable['sku'],
                    'quantity' => $quantity,
                    'unit_price' => $sellable['price'],
                    'line_total' => $line['line_total'],
                ]);

                if ($sellable['stock_mode'] === 'inventory') StockMovement::query()->create([
                    'product_id' => $sellable['product_id'],
                    'product_variant_id' => $sellable['variant_id'],
                    'type' => 'out',
                    'quantity' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockBefore - $quantity,
                    'reference' => $sale->invoice_number,
                    'note' => 'Penjualan POS oleh '.auth()->user()->name,
                    'occurred_at' => $sale->sold_at,
                ]);

                $this->consumeRawMaterials($sale, $sellable['product_id'], $quantity);
            }

            app(CashierShiftSummaryService::class)->refresh($shift);

            return $sale->load('items', 'shift.user');
        });

        $setting = StoreSetting::current();

        return response()->json([
            'message' => 'Transaksi '.$sale->invoice_number.' berhasil diselesaikan.',
            'sale' => [
                'invoice_number' => $sale->invoice_number,
                'store' => [
                    'name' => $setting->store_name,
                    'tagline' => $setting->tagline,
                    'address' => $setting->address,
                    'instagram' => $setting->instagram,
                    'phone' => $setting->phone,
                    'logo_url' => $setting->logo_path ? Storage::url($setting->logo_path) : null,
                ],
                'receipt' => [
                    'footer_note' => $setting->receipt_footer_note,
                    'paper_width' => $setting->receipt_paper_width,
                    'show_logo' => $setting->receipt_show_logo,
                    'show_store_address' => $setting->receipt_show_store_address,
                    'show_cashier' => $setting->receipt_show_cashier,
                ],
                'printer' => [
                    'auto_print' => $setting->printer_auto_print,
                    'close_after_print' => $setting->printer_close_after_print,
                    'connection_mode' => $setting->printer_connection_mode,
                    'bluetooth_service_uuid' => $setting->printer_bluetooth_service_uuid,
                    'bluetooth_characteristic_uuid' => $setting->printer_bluetooth_characteristic_uuid,
                ],
                'cashier' => $sale->shift?->user?->name,
                'sold_at' => $sale->sold_at?->timezone('Asia/Makassar')->format('d M Y H:i'),
                'payment_method' => $sale->payment_method,
                'complimentary_category' => $sale->complimentary_category,
                'complimentary_recipient_name' => $sale->complimentary_recipient_name,
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount,
                'total' => $sale->total,
                'paid_amount' => $sale->paid_amount,
                'change_amount' => $sale->change_amount,
                'items' => $sale->items->map(fn ($item): array => [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ])->values(),
            ],
            'shift' => $this->shiftPayload($sale->shift),
            'items' => $this->itemsPayload($branchId),
        ]);
    }

    private function productPayload(Product $product, int $branchId): array
    {
        $inventory = app(BranchInventoryManager::class)->forProduct($branchId, $product);

        return [
            'id' => 'product-'.$product->sku,
            'sourceType' => 'product',
            'sourceId' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode ?? '',
            'imageUrl' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
            'category' => $product->category?->code ?? 'umum',
            'categoryName' => $product->category?->name ?? 'Umum',
            'price' => $product->sell_price,
            'stock' => $inventory->stock,
            'stockMode' => $product->stock_mode,
            'type' => 'Produk',
            'unit' => 'pcs',
            'isFavorite' => false,
        ];
    }

    private function variantPayload(Product $product, ProductVariant $variant, int $branchId): array
    {
        $inventory = app(BranchInventoryManager::class)->forVariant($branchId, $variant);

        return [
            'id' => 'variant-'.$variant->id,
            'sourceType' => 'variant',
            'sourceId' => $variant->id,
            'name' => $product->name.' · '.$variant->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku ?? $product->sku.'-'.$variant->id,
            'barcode' => $variant->barcode ?? '',
            'imageUrl' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
            'category' => $product->category?->code ?? 'umum',
            'categoryName' => $product->category?->name ?? 'Umum',
            'price' => $variant->sell_price,
            'stock' => $inventory->stock,
            'stockMode' => $product->stock_mode,
            'type' => 'Varian',
            'unit' => $variant->unit ?: 'pcs',
            'isFavorite' => $variant->is_favorite,
        ];
    }

    private function itemsPayload(int $branchId)
    {
        return Product::query()
            ->with(['category', 'variants' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($branchId): array {
                $payload = $this->productPayload($product, $branchId);
                $payload['variants'] = $product->variants
                    ->map(fn (ProductVariant $variant): array => $this->variantPayload($product, $variant, $branchId))
                    ->values()
                    ->all();

                return $payload;
            })
            ->values();
    }

    private function lockSellable(string $id, int $branchId): array
    {
        if (Str::startsWith($id, 'product-')) {
            $sku = Str::after($id, 'product-');
            $product = Product::query()->where('sku', $sku)->lockForUpdate()->firstOrFail();
            $inventory = app(BranchInventoryManager::class)->forProduct($branchId, $product, true);

            return [
                'model' => $product,
                'inventory' => $inventory,
                'stock_mode' => $product->stock_mode,
                'type' => 'product',
                'product_id' => $product->id,
                'variant_id' => null,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (int) $product->sell_price,
            ];
        }

        if (Str::startsWith($id, 'variant-')) {
            $variant = ProductVariant::query()
                ->whereHas('product')
                ->with('product')
                ->whereKey((int) Str::after($id, 'variant-'))
                ->lockForUpdate()
                ->firstOrFail();
            $inventory = app(BranchInventoryManager::class)->forVariant($branchId, $variant, true);

            return [
                'model' => $variant,
                'inventory' => $inventory,
                'stock_mode' => $variant->product->stock_mode,
                'type' => 'variant',
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'name' => $variant->product->name.' · '.$variant->name,
                'sku' => $variant->sku ?? $variant->product->sku.'-'.$variant->id,
                'price' => (int) $variant->product->sell_price + (int) $variant->sell_price,
            ];
        }

        abort(422, 'Item transaksi tidak valid.');
    }

    private function consumeRawMaterials(PosSale $sale, int $productId, int $soldQuantity): void
    {
        ProductRecipeItem::query()
            ->with('rawMaterial')
            ->where('product_id', $productId)
            ->whereNotNull('raw_material_id')
            ->get()
            ->each(function (ProductRecipeItem $recipeItem) use ($sale, $soldQuantity): void {
                $rawMaterial = RawMaterial::query()
                    ->whereKey($recipeItem->raw_material_id)
                    ->lockForUpdate()
                    ->first();

                if (! $rawMaterial) {
                    return;
                }

                $used = (float) $recipeItem->quantity * $soldQuantity;
                $rawMaterial->update([
                    'stock' => max(0, (float) $rawMaterial->stock - $used),
                ]);
                $usage = $sale->rawMaterialUsages()->firstOrNew(['raw_material_id' => $rawMaterial->id]);
                $usage->fill([
                    'quantity' => (float) ($usage->quantity ?? 0) + $used,
                    'unit' => $recipeItem->unit,
                    'is_legacy_fallback' => false,
                ])->save();
            });
    }

    private function refreshShiftTotals(CashierShift $shift): void
    {
        app(CashierShiftSummaryService::class)->refresh($shift);
    }

    private function nextInvoiceNumber(): string
    {
        return 'POS-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
    }

    private function shiftPayload(CashierShift $shift): array
    {
        return [
            'id' => $shift->id,
            'cashier' => $shift->user?->name ?? 'Kasir',
            'branch' => $shift->branch?->name ?? 'Cabang',
            'openedAt' => $shift->opened_at?->timezone('Asia/Makassar')->format('d M Y H:i'),
            'closedAt' => $shift->closed_at?->timezone('Asia/Makassar')->format('d M Y H:i'),
            'salesCount' => $shift->sales_count,
            'netSales' => $shift->net_sales,
            'cashTotal' => $shift->cash_total,
            'qrisTotal' => $shift->qris_total,
            'cardTotal' => $shift->card_total,
            'openingCashAmount' => $shift->opening_cash_amount,
            'cashInDrawer' => $shift->opening_cash_amount + $shift->cash_total,
            'validatedCashAmount' => $shift->validated_cash_amount,
            'validatedCardAmount' => $shift->validated_card_amount,
            'handoverValidatedAt' => $shift->handover_validated_at?->timezone('Asia/Makassar')->format('d M Y H:i'),
        ];
    }
}
