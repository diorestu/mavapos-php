<?php

namespace App\Services\Partner;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSalePayment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SaleRefund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Sandbox
{
    public function outlets(Request $request): JsonResponse
    {
        return $this->page($request, collect([['id' => 900001, 'name' => 'Sandbox Outlet', 'code' => 'SANDBOX', 'active' => true]]));
    }

    public function products(Request $request): JsonResponse
    {
        $product = new Product(['name' => 'Kopi Sandbox', 'sku' => 'SANDBOX-KOPI', 'sell_price' => 20000]);
        $product->id = 900001;
        $product->updated_at = Carbon::parse('2026-11-13T00:00:00Z');
        $category = new ProductCategory(['name' => 'Minuman']);
        $category->id = 900001;
        $variant = new ProductVariant(['name' => 'Besar', 'sku' => 'SANDBOX-L', 'sell_price' => 5000, 'attributes' => ['size' => 'L'], 'is_active' => true]);
        $variant->id = 900001;
        $product->setRelation('category', $category)->setRelation('variants', collect([$variant]));

        return $this->page($request, collect([ApiResource::product($product)]));
    }

    public function transactions(Request $request): JsonResponse
    {
        return $this->page($request, $this->filtered($request));
    }

    public function transaction(Request $request, int $id): JsonResponse
    {
        $sale = $this->data()->firstWhere('id', $id);
        abort_unless($sale, 404);

        return response()->json(['data' => $sale, 'meta' => $this->meta()]);
    }

    public function summary(Request $request): JsonResponse
    {
        $sales = $this->filtered($request)->where('status', '!=', 'voided');
        $products = $sales->flatMap(fn ($sale) => $sale['items'])->groupBy(fn ($i) => $i['product_id'].'|'.$i['variant_id'].'|'.$i['name'].'|'.$i['sku'])
            ->map(fn ($items) => ['product_id' => $items->first()['product_id'], 'variant_id' => $items->first()['variant_id'], 'name' => $items->first()['name'], 'sku' => $items->first()['sku'], 'quantity' => $items->sum('quantity'), 'gross_sales' => $items->sum('line_total')])->values();
        [$page, $size] = ApiInput::pagination($request);

        return response()->json(['data' => ['transaction_count' => $sales->count(), 'gross_sales' => $sales->sum('subtotal'), 'discount' => $sales->sum('discount'), 'total_sales' => $sales->sum('total'), 'refunded_amount' => $sales->sum('refunded_amount'), 'net_sales' => $sales->sum('net_total'), 'products' => $products->forPage($page, $size)->values()->all()], 'meta' => $this->meta() + ['products' => ['page' => $page, 'per_page' => $size, 'total' => $products->count(), 'last_page' => max(1, (int) ceil($products->count() / $size))]]]);
    }

    public function data(): Collection
    {
        return collect([900001, 900002, 900003])->map(function ($id) {
            $sale = new PosSale(['branch_id' => 900001, 'invoice_number' => 'SANDBOX-'.$id, 'payment_method' => 'cash', 'subtotal' => 50000, 'discount' => 5000, 'total' => 45000, 'paid_amount' => 45000, 'sold_at' => Carbon::parse('2026-11-13T02:00:00Z')]);
            $sale->id = $id;
            $sale->updated_at = Carbon::parse('2026-11-13T02:00:00Z');
            $item = new PosSaleItem(['product_id' => 900001, 'product_variant_id' => 900001, 'item_type' => 'variant', 'name' => 'Kopi Sandbox · Besar', 'sku' => 'SANDBOX-L', 'quantity' => 2, 'unit_price' => 25000, 'line_total' => 50000, 'variant_snapshot' => ['id' => 900001, 'name' => 'Besar', 'attributes' => ['size' => 'L']]]);
            $item->id = $id;
            $sale->setRelation('items', collect([$item]))->setRelation('payments', collect([new PosSalePayment(['payment_method' => 'cash', 'amount' => 45000])]))->setRelation('refunds', collect());
            if ($id === 900002) {
                $sale->voided_at = Carbon::parse('2026-11-13T03:00:00Z');
                $sale->void_reason = 'Sandbox cancellation';
                $sale->updated_at = $sale->voided_at;
            }
            if ($id === 900003) {
                $refund = new SaleRefund(['refund_number' => 'SANDBOX-REF-1', 'amount' => 45000, 'refund_method' => 'cash', 'refunded_at' => Carbon::parse('2026-11-13T04:00:00Z')]);
                $refund->id = 900001;
                $refund->updated_at = $refund->refunded_at;
                $refund->setRelation('items', collect());
                $sale->setRelation('refunds', collect([$refund]));
            }

            return ApiResource::transaction($sale);
        });
    }

    private function filtered(Request $request): Collection
    {
        [$data, $from, $to, $updated] = ApiInput::window($request);
        abort_unless((int) $data['outlet_id'] === 900001, 404);

        return $this->data()->filter(fn ($s) => Carbon::parse($s['sold_at'])->betweenIncluded($from, $to) && Carbon::parse($s['sold_at'])->lt($to)
            && (! $updated || Carbon::parse($s['updated_at'])->gte($updated)) && (! isset($data['status']) || $s['status'] === $data['status']))->values();
    }

    private function page(Request $request, Collection $data): JsonResponse
    {
        [$page, $size] = ApiInput::pagination($request);

        return response()->json(['data' => $data->forPage($page, $size)->values()->all(), 'meta' => $this->meta() + ['page' => $page, 'per_page' => $size, 'total' => $data->count(), 'last_page' => max(1, (int) ceil($data->count() / $size))]]);
    }

    private function meta(): array
    {
        return ['timezone' => 'Asia/Makassar', 'currency' => 'IDR', 'sandbox' => true];
    }
}
