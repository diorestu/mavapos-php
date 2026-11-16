<?php

namespace App\Services\Partner;

use App\Models\PosSale;
use App\Models\Product;
use App\Support\LocalTime;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class ApiResource
{
    public static function time(?DateTimeInterface $value): ?string
    {
        return $value ? Carbon::instance($value)->timezone(LocalTime::TIMEZONE)->toIso8601String() : null;
    }

    public static function transaction(PosSale $sale): array
    {
        $refunded = (int) $sale->refunds->sum('amount');
        $status = $sale->voided_at ? 'voided' : ($sale->refunds->isEmpty() ? 'completed' : (($sale->total > 0 && $refunded >= $sale->total) ? 'refunded' : 'partially_refunded'));
        $updated = $sale->updated_at;
        foreach ($sale->refunds as $refund) {
            if ($refund->updated_at > $updated) {
                $updated = $refund->updated_at;
            }
        }
        $received = $sale->payment_method === 'online_merchant' ? (int) $sale->paid_amount : (int) $sale->total;
        $payments = $sale->payments->map(fn ($p) => ['method' => $p->payment_method, 'amount' => (int) $p->amount])->all();
        if ($payments === [] && $sale->payment_method !== 'split') {
            $payments = [['method' => $sale->payment_method, 'amount' => $received]];
        }

        return [
            'id' => $sale->id, 'outlet_id' => $sale->branch_id, 'transaction_number' => $sale->invoice_number,
            'sold_at' => self::time($sale->sold_at), 'updated_at' => self::time($updated), 'status' => $status,
            'subtotal' => (int) $sale->subtotal, 'discount' => (int) $sale->discount, 'total' => (int) $sale->total,
            'payment_method' => $sale->payment_method,
            'payments' => $payments, 'paid_amount' => (int) $sale->paid_amount, 'change_amount' => (int) $sale->change_amount,
            'received_amount' => $received, 'online_merchant' => $sale->online_merchant, 'merchant_deduction' => (int) $sale->online_merchant_deduction,
            'refunded_amount' => $refunded, 'net_total' => $sale->voided_at ? 0 : $sale->total - $refunded,
            'voided_at' => self::time($sale->voided_at), 'void_reason' => $sale->void_reason,
            'refunds' => $sale->refunds->map(fn ($r) => ['id' => $r->id, 'number' => $r->refund_number, 'amount' => (int) $r->amount, 'method' => $r->refund_method, 'refunded_at' => self::time($r->refunded_at), 'items' => $r->items->map(fn ($i) => ['transaction_item_id' => $i->pos_sale_item_id, 'quantity' => (int) $i->quantity, 'amount' => (int) $i->amount])->all()])->all(),
            'items' => $sale->items->map(fn ($i) => [
                'id' => $i->id, 'product_id' => $i->product_id, 'variant_id' => $i->product_variant_id,
                'name' => $i->name, 'sku' => $i->sku, 'quantity' => (int) $i->quantity,
                'unit_price' => (int) $i->unit_price, 'line_total' => (int) $i->line_total,
                'discount' => null, 'variant' => $i->variant_snapshot, 'modifiers' => null,
            ])->all(),
        ];
    }

    public static function product(Product $product): array
    {
        return ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => (int) $product->sell_price,
            'category' => $product->category ? ['id' => $product->category->id, 'name' => $product->category->name] : null,
            'variants' => $product->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'sku' => $v->sku, 'price' => (int) $product->sell_price + (int) $v->sell_price, 'price_adjustment' => (int) $v->sell_price, 'attributes' => $v->attributes, 'active' => (bool) $v->is_active])->all(),
            'updated_at' => self::time($product->updated_at)];
    }
}
