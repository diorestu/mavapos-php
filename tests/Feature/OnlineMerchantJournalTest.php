<?php

use App\Http\Controllers\ReportController;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('journal separates merchant revenue accounts and descriptions from direct sales in screen and financial export', function () {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
    $branch = app(BranchContext::class)->active();
    $shift = CashierShift::query()->create(['user_id' => auth()->id(), 'branch_id' => $branch->id, 'opened_at' => now()]);
    $base = ['cashier_shift_id' => $shift->id, 'user_id' => auth()->id(), 'branch_id' => $branch->id, 'subtotal' => 20000, 'discount' => 0, 'total' => 20000, 'sold_at' => now()];
    PosSale::query()->create([...$base, 'invoice_number' => 'DIRECT', 'payment_method' => 'cash', 'paid_amount' => 20000]);
    foreach (PosSale::ONLINE_MERCHANTS as $merchant => $label) {
        PosSale::query()->create([...$base, 'invoice_number' => $merchant, 'online_merchant' => $merchant, 'payment_method' => 'online_merchant', 'discount' => 2000, 'total' => 18000, 'paid_amount' => 15000]);
    }
    $response = $this->get(route('reports.journal'))->assertOk();
    $screen = $response->viewData('lines');
    $controller = app(ReportController::class);
    $data = (new ReflectionMethod($controller, 'financialData'))->invoke($controller, Request::create('/'));
    foreach ([$screen, collect($data['journalLines'])] as $lines) {
        expect((int) $lines->where('creditAccount', 'Penjualan')->sum('credit'))->toBe(20000)
            ->and((int) $lines->where('creditAccount', 'Pendapatan Online Merchant')->sum('credit'))->toBe(80000)
            ->and((int) $lines->sum('debit'))->toBe((int) $lines->sum('credit'));
        foreach (PosSale::ONLINE_MERCHANTS as $merchant => $label) {
            expect((int) $lines->where('description', 'Pendapatan Online Merchant — '.$label)->sum('credit'))->toBe(20000)
                ->and((int) $lines->where('description', 'Penerimaan bersih Online Merchant — '.$label)->sum('debit'))->toBe(15000)
                ->and((int) $lines->where('description', 'Potongan Online Merchant — '.$label)->sum('debit'))->toBe(3000)
                ->and((int) $lines->where('description', 'Diskon penjualan Online Merchant — '.$label)->sum('debit'))->toBe(2000);
        }
    }
    $html = view('pages.reports.financial-pdf', $data)->render();
    foreach (PosSale::ONLINE_MERCHANTS as $label) {
        $response->assertSee('Pendapatan Online Merchant — '.$label);
        expect($html)->toContain('Pendapatan Online Merchant — '.$label);
    }
});

test('legacy merchant journal separates revenue while preserving original cash qris and split debit accounts', function () {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
    $branch = app(BranchContext::class)->active();
    $shift = CashierShift::query()->create(['user_id' => auth()->id(), 'branch_id' => $branch->id, 'opened_at' => now()]);
    foreach (['cash', 'qris', 'split'] as $method) {
        $sale = PosSale::query()->create([
            'cashier_shift_id' => $shift->id, 'user_id' => auth()->id(), 'branch_id' => $branch->id,
            'invoice_number' => 'LEGACY-'.$method, 'online_merchant' => 'grabfood', 'payment_method' => $method,
            'subtotal' => 20000, 'discount' => 2000, 'total' => 18000, 'paid_amount' => 18000, 'sold_at' => now(),
        ]);
        if ($method === 'split') {
            $sale->payments()->createMany([['payment_method' => 'cash', 'amount' => 8000], ['payment_method' => 'qris', 'amount' => 10000]]);
        }
    }
    $lines = $this->get(route('reports.journal'))->assertOk()->viewData('lines');
    expect((int) $lines->where('creditAccount', 'Pendapatan Online Merchant')->sum('credit'))->toBe(60000)
        ->and((int) $lines->where('debitAccount', 'Kas')->sum('debit'))->toBe(26000)
        ->and((int) $lines->where('debitAccount', 'Bank / QRIS')->sum('debit'))->toBe(28000)
        ->and((int) $lines->where('debitAccount', 'Potongan Online Merchant')->sum('debit'))->toBe(0)
        ->and((int) $lines->sum('debit'))->toBe((int) $lines->sum('credit'));
    expect(PosSale::query()->pluck('payment_method')->all())->toBe(['cash', 'qris', 'split']);
});
