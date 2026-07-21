<?php

use App\Models\Billing;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profit estimate only includes paid billing owned by the active account', function () {
    $firstOwner = User::factory()->create(['role' => 'owner']);
    $secondOwner = User::factory()->create(['role' => 'owner']);
    $firstCustomer = Customer::query()->create(['user_id' => $firstOwner->id, 'code' => 'CUST-REPORT-ONE', 'name' => 'Akun Satu', 'status' => 'aktif']);
    $secondCustomer = Customer::query()->create(['user_id' => $secondOwner->id, 'code' => 'CUST-REPORT-TWO', 'name' => 'Akun Dua', 'status' => 'aktif']);
    Billing::query()->create(['user_id' => $firstOwner->id, 'invoice_number' => 'BILL-REPORT-ONE', 'customer_id' => $firstCustomer->id, 'customer_name' => 'Akun Satu', 'title' => 'Tagihan Satu', 'amount' => 100000, 'payment_status' => 'paid']);
    Billing::query()->create(['user_id' => $secondOwner->id, 'invoice_number' => 'BILL-REPORT-TWO', 'customer_id' => $secondCustomer->id, 'customer_name' => 'Akun Dua', 'title' => 'Tagihan Dua', 'amount' => 900000, 'payment_status' => 'paid']);

    $response = $this->actingAs($firstOwner)->get(route('reports'));

    $response->assertOk();
    expect($response->viewData('summary')['paid_revenue'])->toBe(100000)
        ->and($response->viewData('summary')['net_profit_estimate'])->toBe(100000)
        ->and(Billing::query()->pluck('invoice_number')->all())->toBe(['BILL-REPORT-ONE']);
});
