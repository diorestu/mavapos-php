<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityNotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('global search menampilkan menu produk dan invoice', function () {
    $cashier = User::query()->where('email', 'test@example.com')->firstOrFail();
    $cashier->update(['name' => 'Kasir Search']);

    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();

    $this->actingAs($cashier)
        ->getJson(route('global-search', ['q' => 'kasir']))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'menu',
            'title' => 'Kasir',
        ]);

    $this->actingAs($cashier)
        ->getJson(route('global-search', ['q' => $product->sku]))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'product',
            'title' => $product->name,
        ]);

    $this->actingAs($cashier)
        ->postJson(route('pos.shift.start'))
        ->assertOk();

    $checkout = $this->actingAs($cashier)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['id' => 'product-'.$product->sku, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'discount' => 0,
            'paid_amount' => $product->sell_price,
        ])
        ->assertOk();

    $invoice = $checkout->json('sale.invoice_number');

    $this->actingAs($cashier)
        ->getJson(route('global-search', ['q' => $invoice]))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'sale',
            'title' => $invoice,
        ]);
});

test('pencarian dan aktivitas penjualan hanya menampilkan cabang aktif', function () {
    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    $activeBranch = app(\App\Support\BranchContext::class)->active();
    $otherBranch = Branch::query()->create([
        'user_id' => $user->tenantOwnerId(),
        'name' => 'Cabang Lain',
        'code' => 'cabang-lain',
        'is_active' => true,
    ]);
    $otherInvoice = 'INV-CABANG-LAIN-001';
    $otherShift = CashierShift::query()->create([
        'user_id' => $user->id,
        'branch_id' => $otherBranch->id,
        'opened_at' => now(),
    ]);

    PosSale::query()->create([
        'cashier_shift_id' => $otherShift->id,
        'branch_id' => $otherBranch->id,
        'user_id' => $user->id,
        'invoice_number' => $otherInvoice,
        'payment_method' => 'cash',
        'subtotal' => 18000,
        'discount' => 0,
        'total' => 18000,
        'paid_amount' => 18000,
        'change_amount' => 0,
        'sold_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('branches.switch'), ['branch_id' => $activeBranch->id])
        ->assertRedirect();

    $this->actingAs($user)
        ->getJson(route('global-search', ['q' => $otherInvoice]))
        ->assertOk()
        ->assertJsonMissing(['title' => $otherInvoice]);

    expect(app(ActivityNotificationService::class)->buildActivities(100)
        ->pluck('title'))
        ->not->toContain('Transaksi '.$otherInvoice.' selesai');
});
