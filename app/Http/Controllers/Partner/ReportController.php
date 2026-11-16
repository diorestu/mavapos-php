<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\SaleRefund;
use App\Services\Partner\ApiInput;
use App\Services\Partner\ApiResource;
use App\Services\Partner\Sandbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function outlets(Request $request): JsonResponse
    {
        if ($request->attributes->get('partner_key')->sandbox) {
            return app(Sandbox::class)->outlets($request);
        }
        $query = Branch::withoutGlobalScopes()->where('user_id', $this->ownerId($request))->select('id', 'name', 'code', 'is_active')->orderBy('id');

        return $this->page($request, $query, fn ($branch) => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code, 'active' => (bool) $branch->is_active]);
    }

    public function products(Request $request): JsonResponse
    {
        if ($request->attributes->get('partner_key')->sandbox) {
            return app(Sandbox::class)->products($request);
        }
        $owner = $this->ownerId($request);
        $query = Product::withoutGlobalScopes()->where('user_id', $owner)->with(['category' => fn ($q) => $q->withoutGlobalScopes()->where('user_id', $owner), 'variants'])->orderBy('id');

        return $this->page($request, $query, ApiResource::product(...));
    }

    public function transactions(Request $request): JsonResponse
    {
        if ($request->attributes->get('partner_key')->sandbox) {
            return app(Sandbox::class)->transactions($request);
        }
        $query = $this->saleQuery($request)->with(['items', 'payments', 'refunds.items'])->orderBy('sold_at')->orderBy('id');

        return $this->page($request, $query, ApiResource::transaction(...));
    }

    public function transaction(Request $request, int $transaction): JsonResponse
    {
        if ($request->attributes->get('partner_key')->sandbox) {
            return app(Sandbox::class)->transaction($request, $transaction);
        }
        $sale = PosSale::whereHas('branch', fn ($q) => $q->withoutGlobalScopes()->where('user_id', $this->ownerId($request)))
            ->with(['items', 'payments', 'refunds.items'])->findOrFail($transaction);

        return response()->json(['data' => ApiResource::transaction($sale), 'meta' => $this->meta($request)]);
    }

    public function summary(Request $request): JsonResponse
    {
        if ($request->attributes->get('partner_key')->sandbox) {
            return app(Sandbox::class)->summary($request);
        }
        $query = $this->saleQuery($request)->whereNull('voided_at');
        $totals = (clone $query)->selectRaw('COUNT(*) AS transaction_count, COALESCE(SUM(subtotal), 0) AS gross_sales, COALESCE(SUM(discount), 0) AS discount, COALESCE(SUM(total), 0) AS total_sales')->first();
        $saleIds = (clone $query)->select('pos_sales.id');
        $refunds = (int) SaleRefund::whereIn('pos_sale_id', $saleIds)->sum('amount');
        $products = DB::table('pos_sale_items')->whereIn('pos_sale_id', $saleIds)
            ->select('product_id', 'product_variant_id', 'name', 'sku')->selectRaw('SUM(quantity) AS quantity, SUM(line_total) AS gross_sales')
            ->groupBy('product_id', 'product_variant_id', 'name', 'sku')->orderBy('product_id')->orderBy('product_variant_id')->orderBy('name')->orderBy('sku');
        [$page, $perPage] = ApiInput::pagination($request);
        $productPage = $products->paginate($perPage, ['*'], 'page', $page);

        return response()->json(['data' => [
            'transaction_count' => (int) $totals->transaction_count, 'gross_sales' => (int) $totals->gross_sales,
            'discount' => (int) $totals->discount, 'total_sales' => (int) $totals->total_sales, 'refunded_amount' => $refunds,
            'net_sales' => (int) $totals->total_sales - $refunds,
            'products' => $productPage->getCollection()->map(fn ($row) => ['product_id' => $row->product_id, 'variant_id' => $row->product_variant_id, 'name' => $row->name, 'sku' => $row->sku, 'quantity' => (int) $row->quantity, 'gross_sales' => (int) $row->gross_sales])->all(),
        ], 'meta' => $this->meta($request) + ['products' => ['page' => $productPage->currentPage(), 'per_page' => $perPage, 'total' => $productPage->total(), 'last_page' => $productPage->lastPage()]]]);
    }

    private function saleQuery(Request $request): Builder
    {
        [$data, $from, $to, $updated] = ApiInput::window($request);
        $branch = Branch::withoutGlobalScopes()->where('user_id', $this->ownerId($request))->findOrFail($data['outlet_id']);
        $query = PosSale::where('branch_id', $branch->id)->where('sold_at', '>=', $from)->where('sold_at', '<', $to);
        if ($updated) {
            $query->where(fn ($q) => $q->where('updated_at', '>=', $updated)->orWhereHas('refunds', fn ($r) => $r->where('updated_at', '>=', $updated)));
        }
        $status = $data['status'] ?? null;
        if ($status === 'voided') {
            $query->whereNotNull('voided_at');
        } elseif ($status) {
            $query->whereNull('voided_at');
            if ($status === 'completed') {
                $query->whereDoesntHave('refunds');
            } else {
                $query->whereHas('refunds');
                $sum = '(SELECT COALESCE(SUM(amount), 0) FROM sale_refunds WHERE sale_refunds.pos_sale_id = pos_sales.id)';
                if ($status === 'refunded') {
                    $query->where('total', '>', 0)->whereRaw($sum.' >= pos_sales.total');
                } else {
                    $query->where(fn ($q) => $q->where('total', 0)->orWhereRaw($sum.' < pos_sales.total'));
                }
            }
        }

        return $query;
    }

    private function ownerId(Request $request): int
    {
        return $request->attributes->get('partner_key')->owner_id;
    }

    private function page(Request $request, Builder $query, callable $map): JsonResponse
    {
        [$number, $size] = ApiInput::pagination($request);
        $page = $query->paginate($size, ['*'], 'page', $number);

        return response()->json(['data' => $page->getCollection()->map($map)->all(), 'meta' => $this->meta($request) + ['page' => $page->currentPage(), 'per_page' => $size, 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    private function meta(Request $request): array
    {
        return ['timezone' => 'Asia/Makassar', 'currency' => 'IDR', 'sandbox' => (bool) $request->attributes->get('partner_key')->sandbox];
    }
}
