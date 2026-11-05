<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function offlineContextFixture($test): array
{
    $test->travelTo(now()->setTime(12, 0));
    $owner = User::factory()->create(['role' => 'owner']);
    $test->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create(['sku' => 'OFFLINE-CUP', 'name' => 'Kopi', 'sell_price' => 20000, 'stock' => 10, 'stock_mode' => 'inventory']);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 10]);
    $shift = CashierShift::query()->create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()->subHours(2), 'closed_at' => now()->subHour()]);
    $payload = [
        'client_transaction_id' => (string) Str::uuid(), 'client_created_at' => now()->subMinutes(90)->toIso8601String(),
        'cashier_shift_id' => $shift->id, 'cashier_id' => $owner->id, 'expected_total' => 40000,
        'items' => [['id' => 'product-OFFLINE-CUP', 'quantity' => 2]], 'payment_method' => 'cash', 'paid_amount' => 50000,
    ];

    return [$owner, $branch, $product, $shift, $payload];
}

test('offline checkout sync retains original closed shift and captured sale time', function () {
    [$owner, $branch, $product, $shift, $payload] = offlineContextFixture($this);
    $this->postJson(route('pos.checkout'), $payload)->assertOk()->assertJsonPath('sale.total', 40000);
    $sale = PosSale::query()->firstOrFail();
    expect($sale->cashier_shift_id)->toBe($shift->id);
    expect($sale->sold_at->toIso8601String())->toBe(Carbon::parse($payload['client_created_at'])->utc()->toIso8601String());
    expect($shift->fresh()->net_sales)->toBe(40000);
    expect(app(BranchInventoryManager::class)->stockForProduct($branch->id, $product))->toBe(8);
});

test('offline checkout rejects changed prices without charging or reducing stock', function () {
    [$owner, $branch, $product, $shift, $payload] = offlineContextFixture($this);
    $product->update(['sell_price' => 25000]);
    $this->postJson(route('pos.checkout'), $payload)->assertUnprocessable();
    expect(PosSale::query()->count())->toBe(0);
    expect(app(BranchInventoryManager::class)->stockForProduct($branch->id, $product))->toBe(10);
});

test('offline checkout rejects capture outside shift and future dates', function (string $time) {
    [$owner, $branch, $product, $shift, $payload] = offlineContextFixture($this);
    $payload['client_created_at'] = now()->modify($time)->toIso8601String();
    $this->postJson(route('pos.checkout'), $payload)->assertUnprocessable();
    expect(PosSale::query()->count())->toBe(0);
})->with(['-3 hours', '-30 minutes', '+1 hour']);

test('offline checkout cannot use a shift from another branch or another tenant', function () {
    [$owner, $branch, $product, $shift, $payload] = offlineContextFixture($this);
    $other = User::factory()->create(['role' => 'owner']);
    $otherBranch = Branch::query()->create(['user_id' => $other->id, 'name' => 'Other', 'code' => 'OFFLINE-OTHER', 'is_active' => true]);
    $shift->update(['branch_id' => $otherBranch->id, 'user_id' => $other->id]);
    $this->postJson(route('pos.checkout'), $payload)->assertUnprocessable();
    expect(PosSale::query()->count())->toBe(0);
});

test('captured browser checkout supports the password verified cashier handover', function () {
    [$owner, $branch, $product, $oldShift, $payload] = offlineContextFixture($this);
    $first = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $branch->id]);
    $second = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'branch_id' => $branch->id, 'password' => 'password']);
    $this->actingAs($first)->postJson(route('pos.shift.start'))->assertOk();
    $shift = $this->postJson(route('pos.shift.change'), ['cashier_user_id' => $second->id, 'cashier_password' => 'password'])->assertOk()->json('shift');
    $payload['cashier_shift_id'] = $shift['id'];
    $payload['cashier_id'] = $second->id;
    $payload['client_created_at'] = now()->toIso8601String();
    $this->postJson(route('pos.checkout'), $payload)->assertOk();
    expect(PosSale::query()->firstOrFail()->user_id)->toBe($second->id);
    $this->assertAuthenticatedAs($first);
});
