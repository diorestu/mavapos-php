<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\StoreSetting;
use App\Support\LocalTime;

class CashierShiftSummaryService
{
    public function refresh(CashierShift $shift): CashierShift
    {
        $salesQuery = PosSale::query()->active()->where('cashier_shift_id', $shift->id);
        $totals = (clone $salesQuery)
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(total), 0) as net_sales')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN total ELSE 0 END), 0) as qris_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_total")
            ->first();
        $salesCount = (int) PosSaleItem::query()
            ->whereHas('sale', fn ($query) => $query->active()->where('cashier_shift_id', $shift->id))
            ->sum('quantity') + (int) (clone $salesQuery)->doesntHave('items')->count();

        $shift->update([
            'sales_count' => $salesCount, 'gross_sales' => (int) $totals->gross_sales,
            'discount_total' => (int) $totals->discount_total, 'net_sales' => (int) $totals->net_sales,
            'cash_total' => (int) $totals->cash_total, 'qris_total' => (int) $totals->qris_total,
            'card_total' => (int) $totals->card_total,
        ]);

        return $shift->refresh();
    }

    public function recap(CashierShift $shift): array
    {
        $shift->loadMissing(['user', 'branch']);
        $setting = StoreSetting::current();

        return [
            'id' => $shift->id,
            'cashier' => $shift->user?->name ?? 'Kasir',
            'branch' => $shift->branch?->name ?? 'Cabang',
            'openedAt' => LocalTime::format($shift->opened_at, 'd/m/Y H:i', ''),
            'closedAt' => LocalTime::format($shift->closed_at, 'd/m/Y H:i', ''),
            'salesCount' => $shift->sales_count,
            'grossSales' => $shift->gross_sales,
            'discountTotal' => $shift->discount_total,
            'netSales' => $shift->net_sales,
            'cashTotal' => $shift->cash_total,
            'qrisTotal' => $shift->qris_total,
            'cardTotal' => $shift->card_total,
            'openingCashAmount' => $shift->opening_cash_amount,
            'expectedCashInDrawer' => $shift->opening_cash_amount + $shift->cash_total,
            'validatedCashAmount' => $shift->validated_cash_amount,
            'validatedCardAmount' => $shift->validated_card_amount,
            'handoverValidatedAt' => LocalTime::format($shift->handover_validated_at, 'd/m/Y H:i', ''),
            'closingNote' => $shift->closing_note,
            'store' => [
                'name' => $setting->store_name,
                'tagline' => $setting->tagline,
                'address' => $setting->address,
                'phone' => $setting->phone,
                'instagram' => $setting->instagram,
            ],
            'receipt' => [
                'footer_note' => $setting->receipt_footer_note,
                'paper_width' => $setting->receipt_paper_width,
                'show_store_address' => $setting->receipt_show_store_address,
                'show_cashier' => $setting->receipt_show_cashier,
            ],
            'printer' => [
                'connection_mode' => $setting->printer_connection_mode,
                'bluetooth_service_uuid' => $setting->printer_bluetooth_service_uuid,
                'bluetooth_characteristic_uuid' => $setting->printer_bluetooth_characteristic_uuid,
            ],
        ];
    }
}
