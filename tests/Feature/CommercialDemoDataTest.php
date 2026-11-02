<?php

use App\Models\Branch;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('public'));

test('commercial demo adds varied history only to selected business and reruns without duplicating sales', function () {
    $this->travelTo(now()->setDate(2026, 10, 29)->setTime(12, 0));
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $branch->update(['user_id' => $owner->id]);
    $product = Product::query()->create(['sku' => 'PSC40', 'name' => 'Pie Susu Chandra isi 40pcs', 'buy_price' => 20000, 'sell_price' => 50000, 'stock' => 35, 'min_stock' => 5]);
    Product::query()->create(['sku' => 'PSC75', 'name' => 'Pie Susu 75 pcs', 'buy_price' => 45000, 'sell_price' => 100000, 'stock' => 48]);
    $otherOwner = User::factory()->create(['role' => 'owner']);
    $otherBranch = Branch::withoutGlobalScopes()->create(['user_id' => $otherOwner->id, 'code' => 'OTHER', 'name' => 'Usaha lain', 'is_active' => true]);
    $otherProduct = Product::withoutGlobalScopes()->create(['user_id' => $otherOwner->id, 'sku' => 'OTHER-75', 'name' => 'Produk lain', 'sell_price' => 100000, 'stock' => 99, 'image_path' => 'original.webp']);
    $arguments = ['owner' => $owner->id, '--branch' => $branch->id];
    $exitCode = Artisan::call('demo:seed-commercial', $arguments);
    $this->assertSame(0, $exitCode, Artisan::output());
    $sales = PosSale::query()->where('branch_id', $branch->id)->get();
    expect($sales->count())->toBeGreaterThan(500)
        ->and($sales->pluck('payment_method')->unique()->count())->toBe(5)
        ->and($sales->where('payment_method', 'online_merchant')->pluck('online_merchant')->unique()->count())->toBe(4)
        ->and($sales->max('sold_at')->lessThanOrEqualTo(now()))->toBeTrue()
        ->and($product->fresh()->stock)->toBe(35)
        ->and($otherProduct->fresh()->image_path)->toBe('original.webp')
        ->and($otherProduct->fresh()->stock)->toBe(99)
        ->and(PosSale::query()->where('branch_id', $otherBranch->id)->count())->toBe(0);
    $count = $sales->count();
    foreach ($sales->load('items', 'payments') as $sale) {
        expect((int) $sale->items->sum('line_total'))->toBe($sale->subtotal);
        if ($sale->payment_method === 'split') {
            expect((int) $sale->payments->sum('amount'))->toBe($sale->total);
        }
    }
    expect(Artisan::call('demo:seed-commercial', $arguments))->toBe(0)
        ->and(PosSale::query()->where('branch_id', $branch->id)->count())->toBe($count);
    $dashboard = $this->get(route('dashboard'))->assertOk();
    expect(collect($dashboard->viewData('monthlySalesChart')['series'])->filter()->count())->toBe(10)
        ->and(collect($dashboard->viewData('revenueChart')['revenue'])->filter()->unique()->count())->toBeGreaterThan(5);
    expect(collect($dashboard->viewData('topProducts'))->pluck('sold')->unique()->count())->toBeGreaterThanOrEqual(4);
    $dashboard->assertSee('pie-40.webp');
});

test('demo command refuses a branch belonging to another business without writing data', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $other = User::factory()->create(['role' => 'owner']);
    $branch = Branch::withoutGlobalScopes()->create(['user_id' => $other->id, 'code' => 'FORBIDDEN', 'name' => 'Cabang lain', 'is_active' => true]);
    expect(Artisan::call('demo:seed-commercial', ['owner' => $owner->id, '--branch' => $branch->id]))->toBe(1)
        ->and(DB::table('pos_sales')->count())->toBe(0)
        ->and(DB::table('products')->count())->toBe(0);
});

test('unauthenticated demo command initializes intended stock in a later owned branch', function () {
    $other = User::factory()->create(['role' => 'owner']);
    Branch::withoutGlobalScopes()->create(['user_id' => $other->id, 'code' => 'EARLIER', 'name' => 'Cabang pertama', 'is_active' => true]);
    $owner = User::factory()->create(['role' => 'owner']);
    $branch = Branch::withoutGlobalScopes()->create(['user_id' => $owner->id, 'code' => 'TARGET', 'name' => 'Cabang target', 'is_active' => true]);
    Product::withoutGlobalScopes()->create(['user_id' => $owner->id, 'sku' => 'PSC40', 'name' => 'Pie Susu 40pcs', 'buy_price' => 20000, 'sell_price' => 50000, 'stock' => 35]);
    expect(auth()->check())->toBeFalse();
    expect(Artisan::call('demo:seed-commercial', ['owner' => $owner->id, '--branch' => $branch->id]))->toBe(0);
    $stocks = DB::table('branch_inventories')->join('products', 'products.id', '=', 'branch_inventories.product_id')
        ->where('branch_id', $branch->id)->where('products.sku', 'like', 'DEMO-PSC-'.$owner->id.'-%')
        ->orderBy('products.id')->pluck('branch_inventories.stock')->all();
    expect($stocks)->toBe([8, 145, 21]);
    expect(DB::table('branch_inventories')->join('products', 'products.id', '=', 'branch_inventories.product_id')->where('branch_id', $branch->id)->where('products.sku', 'PSC40')->count())->toBe(0);
});
