<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer page shows loyalty statistics only for the active account', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $otherOwner = User::factory()->create(['role' => 'owner']);
    Customer::query()->create(['user_id' => $owner->id, 'code' => 'CUST-LOYAL-STATS-ONE', 'name' => 'Nina Loyal', 'status' => 'aktif', 'loyalty_stamp_count' => 6, 'loyalty_fifty_reward_available' => true]);
    Customer::query()->create(['user_id' => $owner->id, 'code' => 'CUST-LOYAL-STATS-TWO', 'name' => 'Dina Loyal', 'status' => 'aktif', 'loyalty_stamp_count' => 2, 'loyalty_free_reward_available' => true]);
    Customer::query()->create(['user_id' => $otherOwner->id, 'code' => 'CUST-LOYAL-STATS-OTHER', 'name' => 'Akun Lain', 'status' => 'aktif', 'loyalty_stamp_count' => 10, 'loyalty_fifty_reward_available' => true, 'loyalty_free_reward_available' => true]);

    $response = $this->actingAs($owner)->get(route('customers'));

    $response->assertOk()->assertSee('Statistik Loyalitas')->assertSee('Nina Loyal')->assertSee('Dina Loyal')->assertDontSee('Akun Lain');
    expect($response->viewData('loyaltyStats'))->toBe([
        'stamps' => 8,
        'fiftyRewardCustomers' => 1,
        'freeCupRewardCustomers' => 1,
    ]);
});
