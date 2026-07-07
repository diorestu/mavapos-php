<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Support\BranchContext;
use Illuminate\Http\Request;
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
}
