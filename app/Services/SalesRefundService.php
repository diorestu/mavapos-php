<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\SaleRefund;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\BranchInventoryManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesRefundService
{
    public function __construct(private CashierShiftSummaryService $shiftSummary) {}

    public function refund(PosSale $sale, int $branchId, User $actor, array $data): SaleRefund
    {
        abort_unless($actor->hasRole(['owner', 'admin']), 403);
        $data = Validator::make($data, [
            'client_refund_id' => ['required', 'uuid'],
            'refund_method' => ['required', 'in:'.implode(',', array_keys(SaleRefund::METHODS))],
            'reason' => ['required', 'string', 'max:500'],
            'money_returned' => ['required', 'accepted'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.pos_sale_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.restock' => ['required', 'boolean'],
        ])->validate();
        $data['reason'] = trim($data['reason']);
        if ($data['reason'] === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan refund wajib diisi.']);
        }
        $lines = collect($data['items'])->map(fn ($line) => [
            'pos_sale_item_id' => (int) $line['pos_sale_item_id'], 'quantity' => (int) $line['quantity'], 'restock' => (bool) $line['restock'],
        ])->sortBy('pos_sale_item_id')->values();
        $hash = hash('sha256', json_encode([$sale->id, $branchId, $actor->id, $data['refund_method'], $data['reason'], $lines->all()], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($sale, $branchId, $actor, $data, $lines, $hash) {
            // The tenant lock also serializes reuse of a key against different sales.
            User::query()->whereKey($actor->tenantOwnerId())->lockForUpdate()->firstOrFail();
            Branch::withoutGlobalScope('tenant')->whereKey($branchId)->where(function ($query) use ($actor) {
                $query->where('user_id', $actor->tenantOwnerId());
                if (User::query()->where('role', 'owner')->count() === 1) {
                    $query->orWhereNull('user_id');
                }
            })->firstOrFail();
            $sale = PosSale::query()->whereKey($sale->id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
            $existing = SaleRefund::query()->where('tenant_id', $actor->tenantOwnerId())->where('client_refund_id', $data['client_refund_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'ID refund sudah digunakan dengan data berbeda.');

                return $existing->load('items');
            }
            abort_if($sale->voided_at, 422, 'Transaksi yang sudah dibatalkan tidak dapat direfund.');
            $items = $sale->items()->orderBy('id')->lockForUpdate()->get();
            $allocations = $this->allocations($sale, $items);
            $refundLines = [];
            foreach ($lines as $line) {
                $item = $items->firstWhere('id', $line['pos_sale_item_id']);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Item bukan bagian dari transaksi ini.']);
                }
                $returned = (int) $item->refundItems()->sum('quantity');
                if ($returned + $line['quantity'] > $item->quantity) {
                    throw ValidationException::withMessages(['items' => 'Jumlah retur '.$item->name.' melebihi jumlah tersisa.']);
                }
                if ($line['restock'] && ($item->product?->stock_mode !== 'inventory' || ($item->product_variant_id && ! $item->productVariant))) {
                    throw ValidationException::withMessages(['items' => 'Stok '.$item->name.' tidak dapat dikembalikan. Bahan resep tidak dipulihkan.']);
                }
                $cap = $allocations[$item->id];
                $amount = intdiv($cap * ($returned + $line['quantity']), $item->quantity) - intdiv($cap * $returned, $item->quantity);
                $refundLines[] = ['item' => $item, 'quantity' => $line['quantity'], 'restock' => $line['restock'], 'amount' => $amount];
            }
            $shift = CashierShift::query()->where('branch_id', $branchId)->where('user_id', $actor->id)->whereNull('closed_at')->latest('opened_at')->lockForUpdate()->first();
            $refund = SaleRefund::query()->create([
                'tenant_id' => $actor->tenantOwnerId(), 'branch_id' => $branchId, 'pos_sale_id' => $sale->id, 'user_id' => $actor->id,
                'cashier_shift_id' => $shift?->id, 'client_refund_id' => $data['client_refund_id'], 'request_hash' => $hash,
                'refund_number' => 'RF-'.Str::upper((string) Str::uuid()), 'amount' => array_sum(array_column($refundLines, 'amount')),
                'refund_method' => $data['refund_method'], 'reason' => $data['reason'], 'refunded_at' => now(),
            ]);
            foreach ($refundLines as $line) {
                $item = $line['item'];
                $refund->items()->create(['pos_sale_item_id' => $item->id, 'quantity' => $line['quantity'], 'restock' => $line['restock'], 'amount' => $line['amount']]);
                if (! $line['restock']) {
                    continue;
                }
                $inventory = $item->product_variant_id
                    ? app(BranchInventoryManager::class)->forVariant($branchId, $item->productVariant, true)
                    : app(BranchInventoryManager::class)->forProduct($branchId, $item->product, true);
                $before = (int) $inventory->stock;
                $after = $before + $line['quantity'];
                $inventory->update(['stock' => $after]);
                ($item->productVariant ?: $item->product)->update(['stock' => $after]);
                StockMovement::query()->create([
                    'branch_id' => $branchId, 'created_by_user_id' => $actor->id, 'product_id' => $item->product_id, 'product_variant_id' => $item->product_variant_id,
                    'type' => 'in', 'quantity' => $line['quantity'], 'stock_before' => $before, 'stock_after' => $after,
                    'reference' => $refund->refund_number, 'note' => 'Retur '.$sale->invoice_number.': '.$data['reason'], 'occurred_at' => $refund->refunded_at,
                ]);
            }
            if ($shift) {
                $this->shiftSummary->refresh($shift);
            }

            return $refund->load('items');
        }, 3);
    }

    public function allocations(PosSale $sale, $items): array
    {
        $gross = (int) $items->sum('line_total');
        $net = max(0, $sale->payment_method === 'online_merchant' ? min($sale->paid_amount, $sale->total) : $sale->total);
        $cumulative = 0;
        $allocations = [];
        foreach ($items->sortBy('id') as $item) {
            // Allocate line remainders once, then quantity remainders cumulatively on that line.
            $before = $gross > 0 ? intdiv($net * $cumulative, $gross) : 0;
            $cumulative += $item->line_total;
            $allocations[$item->id] = $gross > 0 ? intdiv($net * $cumulative, $gross) - $before : 0;
        }

        return $allocations;
    }
}
