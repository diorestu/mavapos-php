<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

test('owner can download monthly daily bonuses for the active kiosk', function () {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00', 'Asia/Makassar'));
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $branch->update(['name' => 'Kios Sanur']);
    $cashier = User::factory()->create(['name' => 'Ayu', 'role' => 'kasir', 'tenant_owner_id' => $owner->id]);
    $companion = User::factory()->create(['name' => 'Budi', 'role' => 'kasir', 'tenant_owner_id' => $owner->id]);
    $otherOwner = User::factory()->create(['role' => 'owner']);
    User::factory()->create(['name' => 'Staf Tenant Lain', 'role' => 'kasir', 'tenant_owner_id' => $otherOwner->id]);
    $otherBranch = Branch::query()->create(['name' => 'Kios Lain', 'code' => 'OTHER', 'user_id' => $owner->id, 'is_active' => true]);

    foreach ([[$branch, '2026-02-01'], [$branch, '2026-02-28'], [$branch, '2026-03-01'], [$otherBranch, '2026-02-02']] as $index => [$location, $date]) {
        $soldAt = Carbon::parse($date.' 00:15:00', 'Asia/Makassar')->utc();
        $shift = CashierShift::query()->create([
            'user_id' => $cashier->id, 'branch_id' => $location->id,
            'companion_staff_ids' => [$companion->id],
            'opened_at' => $soldAt->copy()->subMinutes(5), 'closed_at' => $soldAt->copy()->addHour(),
        ]);
        $sale = PosSale::query()->create([
            'user_id' => $cashier->id, 'branch_id' => $location->id, 'cashier_shift_id' => $shift->id,
            'invoice_number' => 'BONUS-PDF-'.$index, 'sold_at' => $soldAt,
            'payment_method' => 'cash', 'subtotal' => 410000, 'total' => 410000,
        ]);
        PosSaleItem::query()->create(['pos_sale_id' => $sale->id, 'sku' => 'BONUS-ITEM', 'name' => 'Produk', 'item_type' => 'product', 'quantity' => 41, 'unit_price' => 10000, 'line_total' => 410000]);
    }

    $page = $this->get('/payrolls?month=2026-02&tab=bonus')->assertOk();
    $page->assertSee('/payrolls/bonus/download?month=2026-02', false);
    expect($page->viewData('month')->format('Y-m'))->toBe('2026-02');

    $data = [];
    View::composer('pages.payrolls.bonus-pdf', function ($view) use (&$data) {
        $data = $view->getData();
    });
    $response = $this->get('/payrolls/bonus/download?month=2026-02')->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
    expect($response->headers->get('content-disposition'))->toContain('attachment;', '2026-02', 'kios-sanur');
    expect($data['bonusTable']['rows'])->toHaveCount(28);
    expect($data['bonusTable']['bonusTotal'])->toBe(100000);
    expect($data['bonusTable']['salesTotal'])->toBe(82);
    expect($data['bonusTable']['salesAmountTotal'])->toBe(820000);
    expect($data['bonusTable']['rows'][0]['salesAmount'])->toBe(410000);
    expect($data['bonusTable']['rows'][1]['salesAmount'])->toBe(0);
    expect($data['bonusTable']['totals']->get($cashier->id))->toBe(50000);
    expect($data['bonusTable']['totals']->get($companion->id))->toBe(50000);
    expect($data['bonusTable']['rows'][0]['values']->get($companion->id))->toBe(25000);
    expect($data['bonusTable']['rows'][27]['values']->get($cashier->id))->toBe(25000);
    $html = view('pages.payrolls.bonus-pdf', $data)->render();
    expect($html)->toContain('41 / 41', '0 / 41', '0 / 0', '82 / 82', '0 / 82');
    $page->assertSee('41 / 41')->assertSee('0 / 41')->assertSee('0 / 0');
    expect($html)->toContain('Qty Terjual (Kasir / Cabang)', 'Nominal Penjualan (Kios)', 'Rp820.000', 'Rp410.000', 'Kios Sanur', 'Ayu', 'Budi', '01/02/2026', '28/02/2026', 'Rp50.000', 'Rp100.000')->not->toContain('Staf Tenant Lain', 'Kios Lain', '01/03/2026');

    $this->post(route('branches.switch'), ['branch_id' => $otherBranch->id])->assertRedirect();
    $this->get('/payrolls/bonus/download?month=2026-02')->assertOk();
    expect($data['activeBranch']->id)->toBe($otherBranch->id);
    expect($data['bonusTable']['bonusTotal'])->toBe(50000);
    expect($data['bonusTable']['salesTotal'])->toBe(41);
    expect($data['bonusTable']['salesAmountTotal'])->toBe(410000);
    expect($data['bonusTable']['totals']->get($cashier->id))->toBe(25000);
});

test('bonus PDF handles a month with no staff or bonus data', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner)->get('/payrolls/bonus/download?month=2026-02')
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('bonus PDF rejects malformed months', function (string $month) {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner)->getJson('/payrolls/bonus/download?month='.$month)
        ->assertUnprocessable()->assertJsonValidationErrors('month');
})->with(['2026-13', 'invalid', '2026-02-01']);

test('bonus PDF retains owner only payroll access', function () {
    $this->get('/payrolls/bonus/download?month=2026-02')->assertRedirect(route('signin'));
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get('/payrolls/bonus/download?month=2026-02')->assertForbidden();
});

test('daily bonus sales totals include all kiosk sales after discount without duplicating item lines', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $cashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id]);
    $this->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $soldAt = Carbon::parse('2026-02-28 23:59:00', 'Asia/Makassar')->utc();
    $shift = CashierShift::query()->create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => $soldAt->copy()->subHour(), 'closed_at' => $soldAt]);
    $sale = PosSale::query()->create([
        'user_id' => $owner->id, 'branch_id' => $branch->id, 'cashier_shift_id' => $shift->id,
        'invoice_number' => 'BONUS-KIOS-TOTAL', 'sold_at' => $soldAt,
        'payment_method' => 'online_merchant', 'online_merchant' => 'grabfood',
        'subtotal' => 50000, 'discount' => 5000, 'total' => 45000, 'paid_amount' => 40000,
    ]);
    foreach ([2, 3] as $quantity) {
        PosSaleItem::query()->create(['pos_sale_id' => $sale->id, 'item_type' => 'product', 'sku' => 'BONUS-QTY-'.$quantity, 'name' => 'Produk', 'quantity' => $quantity, 'unit_price' => 10000, 'line_total' => $quantity * 10000]);
    }
    $sale->update(['user_id' => $cashier->id]);
    $voided = $sale->replicate();
    $voided->fill(['invoice_number' => 'BONUS-VOIDED', 'voided_at' => now()])->save();

    $response = $this->get('/payrolls?month=2026-02&tab=bonus')->assertOk();
    $data = $response->viewData('bonusTable');
    expect($data['rows'][27]['sales'])->toBe(5);
    expect($data['rows'][27]['salesValues']->get($cashier->id))->toBe(5);
    expect($data['cashierSalesTotals']->get($cashier->id))->toBe(5);
    expect($data['rows'][27]['salesAmount'])->toBe(45000);
    expect($data['salesTotal'])->toBe(5);
    expect($data['salesAmountTotal'])->toBe(45000);
    expect($data['bonusTotal'])->toBe(0);
    $response->assertSee('Total Kuantitas')->assertSee('Total Nominal Penjualan')->assertSee('Rp45.000');
});
