<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\StoreSetting;
use App\Support\LocalTime;
use Illuminate\Support\Carbon;

class SalesBonusService
{
    public function monthlyPersonalBonus(int $branchId, Carbon $month, $users): array
    {
        $rows = [];
        $totals = collect($users)->mapWithKeys(fn ($user): array => [$user->id => 0]);
        $salesTotals = collect($users)->mapWithKeys(fn ($user): array => [$user->id => 0]);

        for ($date = $month->copy()->startOfMonth(); $date->lte($month->copy()->endOfMonth()); $date->addDay()) {
            $daily = $this->forBranchDay($branchId, $date);
            $dailyByUser = collect($daily['staffBreakdown'])->keyBy('userId');
            $dailySales = collect($users)->sum(fn ($user): int => (int) ($dailyByUser->get($user->id)['salesCount'] ?? 0));
            $salesValues = collect($users)->mapWithKeys(fn ($user): array => [$user->id => (int) ($dailyByUser->get($user->id)['salesCount'] ?? 0)]);
            $levels = collect($users)->mapWithKeys(fn ($user): array => [$user->id => (int) ($dailyByUser->get($user->id)['tier'] ?? 0)]);
            $values = collect($users)->mapWithKeys(function ($user) use ($dailyByUser, $totals): array {
                $bonus = (int) ($dailyByUser->get($user->id)['bonus'] ?? 0);
                $totals[$user->id] += $bonus;
                return [$user->id => $bonus];
            });
            $dailyBonus = $values->sum();
            $rows[] = ['date' => $date->copy(), 'sales' => $dailySales, 'bonus' => $dailyBonus, 'values' => $values, 'levels' => $levels, 'salesValues' => $salesValues];
            $salesTotals['total'] = ($salesTotals['total'] ?? 0) + $dailySales;
            $totals['total'] = ($totals['total'] ?? 0) + $dailyBonus;
        }

        return ['rows' => $rows, 'totals' => $totals, 'salesTotal' => (int) $salesTotals['total'], 'bonusTotal' => (int) $totals['total']];
    }

    public function forBranchDay(int $branchId, Carbon $day): array
    {
        $localDay = $day->copy()->setTimezone(LocalTime::TIMEZONE);
        $from = $localDay->copy()->startOfDay()->utc();
        $to = $localDay->copy()->endOfDay()->utc();
        $salesQuery = PosSale::query()->active()->where('branch_id', $branchId)->whereBetween('sold_at', [$from, $to]);
        $salesCount = (int) PosSaleItem::query()
            ->whereHas('sale', fn ($query) => $query->active()->where('branch_id', $branchId)->whereBetween('sold_at', [$from, $to]))
            ->sum('quantity') + (int) (clone $salesQuery)->doesntHave('items')->count();
        $tiers = StoreSetting::current()->sales_bonus_tiers ?: StoreSetting::defaults()['sales_bonus_tiers'];
        $bonusTier = collect($tiers)
            ->sortByDesc('minimum')
            ->first(fn (array $tier): bool => $salesCount >= (int) $tier['minimum']);
        $bonus = (int) ($bonusTier['reward'] ?? 0);
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
        $orderedTiers = collect($tiers)->sortBy('minimum')->values();
        $tierIndex = $bonusTier ? $orderedTiers->search(fn (array $tier): bool => (int) $tier['minimum'] === (int) $bonusTier['minimum']) + 1 : 0;
        $staffBreakdown = $staffIds->map(function ($staffId) use ($salesByStaff, $bonus, $tierIndex): array {
            $staffSalesCount = (int) ($salesByStaff->get($staffId) ?? 0);

            return [
                'userId' => (int) $staffId,
                'salesCount' => $staffSalesCount,
                'targetReached' => $bonus > 0,
                'bonus' => $bonus,
                'tier' => $tierIndex,
            ];
        })->values();

        return [
            'salesCount' => $salesCount,
            'targetReached' => $bonus > 0,
            'bonusPerPerson' => $bonus,
            'staffCount' => $staffIds->count(),
            'totalBonus' => $staffBreakdown->sum('bonus'),
            'staffIds' => $staffIds->all(),
            'staffBreakdown' => $staffBreakdown->all(),
        ];
    }

    public function forUserMonth(int $userId, int $branchId, Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $sales = PosSale::query()->active()->where('branch_id', $branchId)->where('user_id', $userId)->whereBetween('sold_at', [$from, $to]);
        $count = (int) PosSaleItem::query()->whereHas('sale', fn ($q) => $q->active()->where('branch_id', $branchId)->where('user_id', $userId)->whereBetween('sold_at', [$from, $to]))->sum('quantity')
            + (int) (clone $sales)->doesntHave('items')->count();
        $bonus = 0;
        for ($date = $month->copy()->startOfMonth(); $date->lte($month->copy()->endOfMonth()); $date->addDay()) {
            $row = collect($this->forBranchDay($branchId, $date)['staffBreakdown'])->firstWhere('userId', $userId);
            $bonus += (int) ($row['bonus'] ?? 0);
        }

        return ['salesCount' => $count, 'bonus' => $bonus];
    }
}
