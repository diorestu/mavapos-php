<?php

use App\Models\Product;
use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('mobile api dapat login dan mengembalikan token', function () {
    $user = User::factory()->create(['email' => 'mobile@example.com', 'password' => 'password123', 'role' => 'owner']);

    $this->postJson('/api/mobile/v1/login', ['email' => $user->email, 'password' => 'password123'])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role'], 'branch']);
});

test('mobile api dapat memulai shift dan checkout', function () {
    $user = User::factory()->create(['role' => 'owner']);
    $this->actingAs($user, 'sanctum');
    $branch = Branch::query()->create(['user_id' => $user->id, 'name' => 'Mobile Branch', 'code' => 'mobile-branch', 'is_active' => true]);
    $product = Product::query()->create(['user_id' => $user->id, 'sku' => 'MOBILE-001', 'name' => 'Produk Mobile', 'sell_price' => 18000, 'stock' => 5, 'stock_mode' => 'inventory']);
    app(BranchInventoryManager::class)->forProduct($branch->id, $product)->update(['stock' => 5]);

    $this->postJson('/api/mobile/v1/shifts/start', ['branch_id' => $branch->id])->assertOk();
    $this->postJson('/api/mobile/v1/checkout', ['branch_id' => $branch->id, 'items' => [['id' => 'product-MOBILE-001', 'quantity' => 1]], 'payment_method' => 'cash', 'paid_amount' => 20000])
        ->assertOk()
        ->assertJsonPath('sale.total', 18000);
});

test('mobile api dapat memuat produk tanpa branch_id untuk branch default', function () {
    $user = User::factory()->create(['role' => 'owner']);
    $this->actingAs($user, 'sanctum');
    Branch::query()->create(['user_id' => $user->id, 'name' => 'Default Mobile', 'code' => 'default-mobile', 'is_active' => true]);

    $this->getJson('/api/mobile/v1/pos')->assertOk()->assertJsonStructure(['items', 'categories']);
});
