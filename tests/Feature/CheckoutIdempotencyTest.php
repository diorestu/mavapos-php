<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($this->owner);
    $this->branch = app(BranchContext::class)->active();
    $this->product = Product::withoutGlobalScopes()->where('sku', 'SKU-001')->firstOrFail();
    $this->product->update(['user_id' => $this->owner->id]);
    $this->inventory = app(BranchInventoryManager::class)->forProduct($this->branch->id, $this->product);
    $this->inventory->update(['stock' => 10]);
    $this->postJson(route('pos.shift.start'))->assertOk();
    $this->payload = [
        'items' => [['id' => 'product-SKU-001', 'quantity' => 2]],
        'payment_method' => 'cash', 'discount' => 0, 'paid_amount' => 40000,
    ];
    $this->key = (string) Str::uuid();
});

test('checkout replays its original response and consumes stock and payments once', function () {
    $this->payload['payment_method'] = 'split';
    $this->payload['payments'] = [['method' => 'cash', 'amount' => 18000], ['method' => 'qris', 'amount' => 18000]];
    $first = $this->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => $this->key])->assertOk();
    $replay = $this->postJson(route('pos.checkout'), array_reverse($this->payload, true), ['Idempotency-Key' => $this->key])
        ->assertOk()->assertHeader('Idempotent-Replayed', 'true');
    expect($replay->json())->toBe($first->json());
    expect($this->inventory->fresh()->stock)->toBe(8);
    $this->assertDatabaseCount('pos_sales', 1);
    $this->assertDatabaseCount('pos_sale_payments', 2);
    $this->assertDatabaseCount('checkout_requests', 1);
});

test('checkout UUID from body replays across header transport and rejects changed nested payload', function () {
    $payload = [...$this->payload, 'client_transaction_id' => $this->key];
    $first = $this->postJson(route('pos.checkout'), $payload)->assertOk();
    $this->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => $this->key])
        ->assertOk()->assertJsonPath('sale.invoice_number', $first->json('sale.invoice_number'));
    $payload['items'][0]['quantity'] = 1;
    $this->postJson(route('pos.checkout'), $payload)->assertConflict();
    expect($this->inventory->fresh()->stock)->toBe(8);
});

test('checkout rejects UUID mismatch and malformed UUID without selling', function () {
    $this->postJson(route('pos.checkout'), [...$this->payload, 'client_transaction_id' => (string) Str::uuid()], ['Idempotency-Key' => $this->key])->assertUnprocessable();
    $this->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => 'invalid'])->assertUnprocessable();
    $this->assertDatabaseCount('pos_sales', 0);
});

test('checkout failures leave the UUID retryable', function () {
    $this->inventory->update(['stock' => 1]);
    $this->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => $this->key])->assertUnprocessable();
    $this->inventory->update(['stock' => 10]);
    $this->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => $this->key])->assertOk();
    $this->assertDatabaseCount('pos_sales', 1);
    expect($this->inventory->fresh()->stock)->toBe(8);
});

test('checkout UUID is bound to authenticated actor and branch within the tenant', function () {
    $this->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => $this->key])->assertOk();
    $admin = User::factory()->create(['role' => 'admin', 'tenant_owner_id' => $this->owner->id]);
    $this->actingAs($admin)->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => $this->key])->assertConflict();
    $this->actingAs($this->owner);
    $branch = Branch::query()->create(['user_id' => $this->owner->id, 'name' => 'Other', 'code' => 'other', 'is_active' => true]);
    app(BranchContext::class)->setActive($branch->id);
    $this->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => $this->key])->assertConflict();
    $this->assertDatabaseCount('pos_sales', 1);
});

test('checkout without UUID preserves legacy separate sales', function () {
    $this->postJson(route('pos.checkout'), $this->payload)->assertOk();
    $this->postJson(route('pos.checkout'), $this->payload)->assertOk();
    $this->assertDatabaseCount('pos_sales', 2);
    expect($this->inventory->fresh()->stock)->toBe(6);
});

test('replayed checkout never awards additional loyalty stamps', function () {
    StoreSetting::current()->update(['cashier_loyalty_card_enabled' => true]);
    $customer = Customer::query()->create([
        'user_id' => $this->owner->id, 'code' => 'IDEMPOTENT', 'name' => 'Customer', 'phone' => '081234567899',
        'status' => 'active', 'loyalty_stamp_count' => 0,
    ]);
    $payload = [...$this->payload, 'customer_phone' => $customer->phone, 'loyalty_stamp' => true];
    $this->postJson(route('pos.checkout'), $payload, ['Idempotency-Key' => $this->key])->assertOk();
    $stamps = $customer->fresh()->loyalty_stamp_count;
    expect($stamps)->toBeGreaterThan(0);
    $this->postJson(route('pos.checkout'), $payload, ['Idempotency-Key' => $this->key])->assertOk()->assertHeader('Idempotent-Replayed', 'true');
    expect($customer->fresh()->loyalty_stamp_count)->toBe($stamps);
});

test('mobile checkout uses the same UUID replay protection', function () {
    $payload = [...$this->payload, 'branch_id' => $this->branch->id];
    $first = $this->postJson('/api/mobile/v1/checkout', $payload, ['Idempotency-Key' => $this->key])->assertOk();
    $this->postJson('/api/mobile/v1/checkout', $payload, ['Idempotency-Key' => $this->key])->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('sale.invoice_number', $first->json('sale.invoice_number'));
    expect($this->inventory->fresh()->stock)->toBe(8);
});

test('nested object order is canonical while list order and captured metadata stay meaningful', function () {
    $payload = [...$this->payload, 'metadata' => ['z' => ['b' => 2, 'a' => 1], 'a' => 1]];
    $this->postJson(route('pos.checkout'), $payload, ['Idempotency-Key' => $this->key])->assertOk();
    $payload['metadata'] = ['a' => 1, 'z' => ['a' => 1, 'b' => 2]];
    $this->postJson(route('pos.checkout'), $payload, ['Idempotency-Key' => $this->key])->assertOk()->assertHeader('Idempotent-Replayed', 'true');
    $payload['metadata']['z']['b'] = 3;
    $this->postJson(route('pos.checkout'), $payload, ['Idempotency-Key' => $this->key])->assertConflict();
});

test('failure to persist checkout replay rolls back the real sale and stock atomically', function () {
    DB::unprepared("CREATE TRIGGER reject_checkout_ledger BEFORE INSERT ON checkout_requests BEGIN SELECT RAISE(ABORT, 'ledger unavailable'); END");
    $this->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => $this->key])->assertServerError();
    $this->assertDatabaseCount('pos_sales', 0);
    expect($this->inventory->fresh()->stock)->toBe(10);
    DB::unprepared('DROP TRIGGER reject_checkout_ledger');
    $this->postJson(route('pos.checkout'), $this->payload, ['Idempotency-Key' => $this->key])->assertOk();
    $this->assertDatabaseCount('pos_sales', 1);
});

test('captured checkout replays after delegated cashier session resets', function () {
    $cashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $this->owner->id]);
    $shift = CashierShift::query()->create([
        'user_id' => $cashier->id, 'branch_id' => $this->branch->id, 'opened_at' => now()->subHour(),
    ]);
    $payload = [...$this->payload,
        'client_transaction_id' => $this->key, 'client_created_at' => now()->subMinute()->toIso8601String(),
        'cashier_shift_id' => $shift->id, 'cashier_id' => $cashier->id, 'expected_total' => 36000,
    ];
    $this->withSession(['pos_active_cashier_id' => $cashier->id]);
    $first = $this->postJson(route('pos.checkout'), $payload)->assertOk();
    $shift->update(['closed_at' => now()]);
    session()->forget('pos_active_cashier_id');
    $this->postJson(route('pos.checkout'), $payload)->assertOk()->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('sale.invoice_number', $first->json('sale.invoice_number'));
    $this->postJson(route('pos.checkout'), [...$payload, 'cashier_id' => $this->owner->id])->assertConflict();
    $this->assertDatabaseCount('pos_sales', 1);
    expect($this->inventory->fresh()->stock)->toBe(8);
});
