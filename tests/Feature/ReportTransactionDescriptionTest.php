<?php

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('laporan menjelaskan diskon gratis dan rincian split payment setiap transaksi', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $shift = CashierShift::query()->create([
        'user_id' => $owner->id,
        'branch_id' => $branch->id,
        'opened_at' => now()->subHour(),
    ]);

    PosSale::query()->create([
        'cashier_shift_id' => $shift->id,
        'branch_id' => $branch->id,
        'user_id' => $owner->id,
        'invoice_number' => 'REPORT-DISCOUNT-001',
        'payment_method' => 'cash',
        'subtotal' => 20000,
        'discount' => 5000,
        'total' => 15000,
        'sold_at' => now(),
    ]);
    PosSale::query()->create([
        'cashier_shift_id' => $shift->id,
        'branch_id' => $branch->id,
        'user_id' => $owner->id,
        'invoice_number' => 'REPORT-FREE-001',
        'payment_method' => 'free',
        'complimentary_category' => 'influencer',
        'complimentary_recipient_name' => 'Dina Creator',
        'subtotal' => 20000,
        'discount' => 20000,
        'total' => 0,
        'sold_at' => now(),
    ]);
    $splitSale = PosSale::query()->create([
        'cashier_shift_id' => $shift->id,
        'branch_id' => $branch->id,
        'user_id' => $owner->id,
        'invoice_number' => 'REPORT-SPLIT-001',
        'payment_method' => 'split',
        'subtotal' => 300000,
        'discount' => 0,
        'total' => 300000,
        'sold_at' => now(),
    ]);
    $splitSale->payments()->createMany([
        ['payment_method' => 'cash', 'amount' => 200000],
        ['payment_method' => 'qris', 'amount' => 100000],
    ]);

    $query = ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()];

    $this->get(route('reports', $query))
        ->assertOk()
        ->assertSee('Diskon Rp5.000')
        ->assertSee('Gratis — Influencer: Dina Creator')
        ->assertSee('Tunai Rp200.000 + QRIS Rp100.000');

    $this->get(route('reports.excel', $query))
        ->assertOk()
        ->assertSee('Diskon Rp5.000')
        ->assertSee('Gratis — Influencer: Dina Creator')
        ->assertSee('Tunai Rp200.000 + QRIS Rp100.000');

    $this->get(route('reports.journal', $query))
        ->assertOk()
        ->assertSee('Split Payment: Tunai Rp200.000 + QRIS Rp100.000');
});
