<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CashierShiftController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = app(BranchContext::class)->activeId();

        $shifts = CashierShift::query()
            ->with(['user', 'branch'])
            ->where('branch_id', $branchId)
            ->withCount('sales')
            ->latest('opened_at')
            ->paginate(12);

        return view('pages.cashier-shifts.index', [
            'title' => 'Shift Kasir',
            'activeShift' => CashierShift::query()
                ->with(['user', 'branch'])
                ->where('branch_id', $branchId)
                ->whereNull('closed_at')
                ->latest('opened_at')
                ->first(),
            'shifts' => $shifts,
        ]);
    }

    public function forceClose(Request $request, CashierShift $cashierShift): RedirectResponse
    {
        $validated = $request->validate([
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $branchId = app(BranchContext::class)->activeId();

        $shift = DB::transaction(function () use ($cashierShift, $validated, $branchId): CashierShift {
            $shift = CashierShift::query()
                ->with('user')
                ->whereKey($cashierShift->id)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($shift->closed_at) {
                return $shift;
            }

            $this->closeShift($shift, $validated['closing_note'] ?? null);

            return $shift->refresh()->load('user');
        });

        return redirect()
            ->route('cashier-shifts')
            ->with('success', 'Shift '.($shift->user?->name ?? 'Kasir').' ditutup paksa.');
    }

    private function closeShift(CashierShift $shift, ?string $closingNote): void
    {
        $totals = PosSale::query()
            ->where('cashier_shift_id', $shift->id)
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(total), 0) as net_sales')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN total ELSE 0 END), 0) as qris_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_total")
            ->first();

        $shift->update([
            'closed_at' => now(),
            'sales_count' => (int) $totals->sales_count,
            'gross_sales' => (int) $totals->gross_sales,
            'discount_total' => (int) $totals->discount_total,
            'net_sales' => (int) $totals->net_sales,
            'cash_total' => (int) $totals->cash_total,
            'qris_total' => (int) $totals->qris_total,
            'card_total' => (int) $totals->card_total,
            'closing_note' => $closingNote,
        ]);
    }
}
