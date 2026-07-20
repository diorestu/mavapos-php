<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use Illuminate\Support\Carbon;

class SalesBonusService
{
    public function forBranchDay(int $branchId, Carbon $day): array
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $salesQuery = PosSale::query()->active()->where('branch_id', $branchId)->whereBetween('sold_at', [$from, $to]);
        $salesCount = (int) PosSaleItem::query()
            ->whereHas('sale', fn ($query) => $query->active()->where('branch_id', $branchId)->whereBetween('sold_at', [$from, $to]))
            ->sum('quantity') + (int) (clone $salesQuery)->doesntHave('items')->count();
        $bonus = match (true) {
            $salesCount >= 101 => 100000,
            $salesCount >= 81 => 75000,
            $salesCount >= 61 => 50000,
            $salesCount >= 41 => 25000,
            default => 0,
        };
        $shifts = CashierShift::query()->where('branch_id', $branchId)
            ->where('opened_at', '<=', $to)
            ->where(fn ($query) => $query->whereNull('closed_at')->orWhere('closed_at', '>=', $from))->get(['user_id', 'companion_staff_ids']);
        $staffIds = $shifts->flatMap(fn ($shift) => [$shift->user_id, ...($shift->companion_staff_ids ?? [])])->unique()->values();
        $salesByStaff = PosSaleItem::query()
            ->whereHas('sale', fn ($query) => $query->active()->where('branch_id', $branchId)->whereBetween('sold_at', [$from, $to])->whereIn('user_id', $staffIds))
            ->join('pos_sales', 'pos_sale_items.pos_sale_id', '=', 'pos_sales.id')
            ->selectRaw('pos_sales.user_id, SUM(pos_sale_items.quantity) as sales_count')
            ->groupBy('pos_sales.user_id')
            ->pluck('sales_count', 'pos_sales.user_id');
        $legacySalesByStaff = (clone $salesQuery)->doesntHave('items')->whereIn('user_id', $staffIds)
            ->selectRaw('user_id, COUNT(*) as sales_count')->groupBy('user_id')->pluck('sales_count', 'user_id');
        $legacySalesByStaff->each(function ($count, $staffId) use ($salesByStaff): void {
            $salesByStaff[$staffId] = (int) ($salesByStaff->get($staffId) ?? 0) + (int) $count;
        });

        return [
            'salesCount' => $salesCount,
            'targetReached' => $bonus > 0,
            'bonusPerPerson' => $bonus,
            'staffCount' => $staffIds->count(),
            'totalBonus' => $bonus * $staffIds->count(),
            'staffIds' => $staffIds->all(),
            'staffBreakdown' => $staffIds->map(fn ($staffId): array => [
                'userId' => (int) $staffId,
                'salesCount' => (int) ($salesByStaff->get($staffId) ?? 0),
                'bonus' => $bonus,
            ])->all(),
        ];
    }
}
