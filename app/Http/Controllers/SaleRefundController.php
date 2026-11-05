<?php

namespace App\Http\Controllers;

use App\Models\PosSale;
use App\Models\SaleRefund;
use App\Services\SalesRefundService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SaleRefundController extends Controller
{
    public function show(Request $request, PosSale $sale, SalesRefundService $service)
    {
        abort_unless($request->user()->hasRole(['owner', 'admin']), 403);
        $sale = PosSale::query()->whereKey($sale->id)->where('branch_id', app(BranchContext::class)->activeId())
            ->with(['items.product', 'items.productVariant', 'items.refundItems', 'refunds.items.saleItem', 'refunds.user'])->firstOrFail();
        $allocations = $service->allocations($sale, $sale->items);
        $lines = $sale->items->map(fn ($item) => [
            'id' => $item->id, 'cap' => $allocations[$item->id], 'sold' => $item->quantity,
            'returned' => (int) $item->refundItems->sum('quantity'),
        ])->values();

        if ($request->expectsJson()) {
            return response()->json([
                'sale' => $sale->only(['id', 'invoice_number', 'branch_id', 'subtotal', 'discount', 'total', 'payment_method', 'online_merchant', 'voided_at']),
                'refund_methods' => SaleRefund::METHODS,
                'refunded_amount' => (int) $sale->refunds->sum('amount'),
                'items' => $sale->items->map(function ($item) use ($allocations): array {
                    $returned = (int) $item->refundItems->sum('quantity');
                    $cap = $allocations[$item->id];

                    return [
                        'pos_sale_item_id' => $item->id, 'name' => $item->name, 'sku' => $item->sku,
                        'quantity' => $item->quantity, 'returned_quantity' => $returned,
                        'remaining_quantity' => $item->quantity - $returned,
                        'allocated_refund_amount' => $cap,
                        'remaining_refund_amount' => $item->quantity > 0 ? $cap - intdiv($cap * $returned, $item->quantity) : 0,
                        'can_restock' => $item->product?->stock_mode === 'inventory' && (! $item->product_variant_id || $item->productVariant !== null),
                    ];
                })->values(),
                'refunds' => $sale->refunds->map(fn ($refund): array => $refund->only(['id', 'refund_number', 'amount', 'refund_method', 'reason', 'refunded_at']))->values(),
            ]);
        }

        return view('pages.sales.refund', ['title' => 'Retur / Refund', 'sale' => $sale, 'lines' => $lines,
            'methods' => SaleRefund::METHODS, 'clientRefundId' => (string) Str::uuid()]);
    }

    public function store(Request $request, PosSale $sale, SalesRefundService $service)
    {
        $data = $request->all();
        // Normal HTML forms submit unselected rows with zero quantity.
        if (! $request->expectsJson() && is_array($data['items'] ?? null)) {
            $data['items'] = array_values(array_filter($data['items'], fn ($line) => ! is_array($line) || ($line['quantity'] ?? null) !== '0'));
        }
        $refund = $service->refund($sale, app(BranchContext::class)->activeId(), $request->user(), $data);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Refund berhasil dicatat.', 'refund' => [
                'id' => $refund->id, 'refund_number' => $refund->refund_number, 'amount' => $refund->amount,
                'refund_method' => $refund->refund_method, 'refunded_at' => $refund->refunded_at,
            ]]);
        }

        return redirect()->route('sales.refund', $sale)->with('success', 'Refund '.$refund->refund_number.' sebesar Rp '.number_format($refund->amount, 0, ',', '.').' berhasil dicatat.');
    }
}
