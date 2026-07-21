<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\User;
use App\Services\CashierShiftSummaryService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CashierShiftController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = app(BranchContext::class)->activeId();
        $activeSales = fn ($query) => $query->active();

        $shifts = CashierShift::query()
            ->with(['user', 'branch'])
            ->where('branch_id', $branchId)
            ->withCount(['sales as sales_count' => $activeSales])
            ->withSum(['sales as net_sales' => $activeSales], 'total')
            ->withSum(['sales as cash_total' => fn ($query) => $query->active()->where('payment_method', 'cash')], 'total')
            ->withSum(['sales as qris_total' => fn ($query) => $query->active()->where('payment_method', 'qris')], 'total')
            ->withSum(['sales as card_total' => fn ($query) => $query->active()->where('payment_method', 'card')], 'total')
            ->latest('opened_at')
            ->paginate(12);

        return view('pages.cashier-shifts.index', [
            'title' => 'Shift Kasir',
            'activeShift' => CashierShift::query()
                ->with(['user', 'branch'])
                ->where('branch_id', $branchId)
                ->whereNull('closed_at')
                ->withCount(['sales as sales_count' => $activeSales])
                ->withSum(['sales as net_sales' => $activeSales], 'total')
                ->withSum(['sales as cash_total' => fn ($query) => $query->active()->where('payment_method', 'cash')], 'total')
                ->withSum(['sales as qris_total' => fn ($query) => $query->active()->where('payment_method', 'qris')], 'total')
                ->withSum(['sales as card_total' => fn ($query) => $query->active()->where('payment_method', 'card')], 'total')
                ->latest('opened_at')
                ->first(),
            'shifts' => $shifts,
            'availableStaff' => User::query()->where('tenant_owner_id', $request->user()->tenantOwnerId())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function forceClose(Request $request, CashierShift $cashierShift): JsonResponse|RedirectResponse
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

            $shift->update([
                'closed_at' => now(),
                'closing_note' => $validated['closing_note'] ?? null,
            ]);

            app(CashierShiftSummaryService::class)->refresh($shift);

            return $shift->refresh()->load('user');
        });

        $recap = app(CashierShiftSummaryService::class)->recap($shift);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Shift '.($shift->user?->name ?? 'Kasir').' ditutup paksa.',
                'recap' => $recap,
            ]);
        }

        return redirect()
            ->route('cashier-shifts')
            ->with('success', 'Shift '.($shift->user?->name ?? 'Kasir').' ditutup paksa.')
            ->with('shiftRecap', $recap);
    }

    public function update(Request $request, CashierShift $cashierShift): RedirectResponse
    {
        $validated = $request->validate([
            'opening_cash_amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'opened_at' => ['required', 'date'],
            'closed_at' => ['nullable', 'date', 'after_or_equal:opened_at'],
            'opening_note' => ['nullable', 'string', 'max:1000'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
            'companion_staff_ids' => ['nullable', 'array', 'max:20'],
            'companion_staff_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
        $branchId = app(BranchContext::class)->activeId();
        $companionIds = User::query()->where('tenant_owner_id', $request->user()->tenantOwnerId())
            ->whereIn('id', $validated['companion_staff_ids'] ?? [])->pluck('id')->values()->all();

        DB::transaction(function () use ($cashierShift, $validated, $branchId, $companionIds): void {
            $shift = CashierShift::query()->whereKey($cashierShift->id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
            $shift->update([
                'opening_cash_amount' => $validated['opening_cash_amount'],
                'opened_at' => $validated['opened_at'], 'closed_at' => $validated['closed_at'] ?? null,
                'opening_note' => $validated['opening_note'] ?? null, 'closing_note' => $validated['closing_note'] ?? null,
                'companion_staff_ids' => $companionIds,
            ]);
            app(CashierShiftSummaryService::class)->refresh($shift);
        });

        return redirect()->route('cashier-shifts')->with('success', 'Data shift berhasil diperbarui. Total penjualan dihitung ulang dari transaksi.');
    }

    public function destroy(CashierShift $cashierShift): RedirectResponse
    {
        $branchId = app(BranchContext::class)->activeId();

        DB::transaction(function () use ($cashierShift, $branchId): void {
            $shift = CashierShift::query()
                ->whereKey($cashierShift->id)
                ->where('branch_id', $branchId)
                ->whereNotNull('closed_at')
                ->lockForUpdate()
                ->firstOrFail();

            $shift->delete();
        });

        return redirect()
            ->route('cashier-shifts')
            ->with('success', 'Riwayat shift dan transaksi di dalamnya berhasil dihapus.');
    }
}
