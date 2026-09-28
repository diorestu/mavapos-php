<?php

use App\Models\Billing;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('superadmin dapat melihat ringkasan membership lintas tenant', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin', 'tenant_owner_id' => null]);
    $firstOwner = User::factory()->create(['role' => 'owner']);
    $secondOwner = User::factory()->create(['role' => 'owner']);
    Billing::query()->create(['user_id' => $firstOwner->id, 'invoice_number' => 'SAAS-001', 'customer_name' => 'Toko Satu', 'title' => 'Plus', 'amount' => 249000, 'payment_status' => 'paid', 'paid_at' => now(), 'provider_payload' => ['subscription' => ['plan_slug' => 'plus', 'plan_name' => 'Plus Plan', 'period_ends_at' => now()->addMonth()->toDateString()]]]);

    $this->actingAs($superadmin)->get(route('superadmin.memberships'))
        ->assertOk()
        ->assertSee($firstOwner->email)
        ->assertSee($secondOwner->email)
        ->assertSee('249.000', false);
});

test('akun tenant tidak dapat membuka dashboard superadmin', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    $this->actingAs($owner)->get(route('superadmin.memberships'))->assertForbidden();
});

test('seeder superadmin memakai kredensial environment dan idempotent', function () {
    config()->set('superadmin.name', 'Platform Admin');
    config()->set('superadmin.email', 'platform@example.com');
    config()->set('superadmin.password', 'platform-secret');

    Artisan::call('db:seed', ['--class' => SuperAdminSeeder::class]);
    Artisan::call('db:seed', ['--class' => SuperAdminSeeder::class]);

    expect(User::query()->where('email', 'platform@example.com')->where('role', 'superadmin')->count())->toBe(1);
});
