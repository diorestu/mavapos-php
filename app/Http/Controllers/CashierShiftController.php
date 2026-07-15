<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
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
}
