<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashierShiftController extends Controller
{
    public function index(Request $request): View
    {
        $shifts = CashierShift::query()
            ->with('user')
            ->withCount('sales')
            ->latest('opened_at')
            ->paginate(12);

        return view('pages.cashier-shifts.index', [
            'title' => 'Shift Kasir',
            'activeShift' => CashierShift::query()
                ->with('user')
                ->whereNull('closed_at')
                ->latest('opened_at')
                ->first(),
            'shifts' => $shifts,
        ]);
    }
}
