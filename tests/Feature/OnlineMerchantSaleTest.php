<?php

use App\Http\Controllers\ReportController;
use App\Models\Branch;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CashierShiftSummaryService;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\ExcelExport;

uses(RefreshDatabase::class);

function onlineMerchantFixture($test, string $role = 'owner'): array
{
    $owner = User::factory()->create(['role' => $role]);
    $test->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $product = Product::query()->create(['sku' => 'ONLINE-COFFEE', 'name' => 'Kopi Online', 'sell_price' => 20000, 'buy_price' => 5000, 'stock' => 10, 'min_stock' => 0]);
    $inventory = app(BranchInventoryManager::class)->forProduct($branch->id, $product);
    $inventory->update(['stock' => 10]);
    $test->postJson(route('pos.shift.start'), ['opening_cash_amount' => 0])->assertOk();

    return [$inventory, ['items' => [['id' => 'product-ONLINE-COFFEE', 'quantity' => 1]], 'payment_method' => 'qris']];
}

test('online merchant is disabled by default and settings are isolated by branch and tenant', function () {
    onlineMerchantFixture($this);
    $first = StoreSetting::current();
    expect($first->cashier_online_merchant_enabled)->toBeFalse();
    $this->patch(route('settings.update'), ['store_name' => 'Toko', 'cashier_online_merchant_enabled' => '1'])->assertRedirect(route('settings'));
    expect($first->fresh()->cashier_online_merchant_enabled)->toBeTrue();
    $second = Branch::query()->create(['name' => 'Kedua', 'code' => 'online-second', 'is_active' => true]);
    app(BranchContext::class)->setActive($second->id);
    expect(StoreSetting::current()->cashier_online_merchant_enabled)->toBeFalse();
    $this->actingAs(User::factory()->create(['role' => 'owner']));
    expect(StoreSetting::current()->cashier_online_merchant_enabled)->toBeFalse();
    expect($first->fresh()->cashier_online_merchant_enabled)->toBeTrue();
});

test('online checkout records seller net for each merchant and retains inventory calculations', function (string $merchant, string $label) {
    [$inventory, $payload] = onlineMerchantFixture($this);
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $response = $this->postJson(route('pos.checkout'), [...$payload, 'sales_channel' => 'online', 'online_merchant' => $merchant, 'paid_amount' => 16000])->assertOk()
        ->assertJsonPath('sale.online_merchant', $merchant)->assertJsonPath('sale.online_merchant_label', $label)
        ->assertJsonPath('sale.payment_method', 'online_merchant')->assertJsonPath('sale.total', 20000)
        ->assertJsonPath('sale.paid_amount', 16000)->assertJsonPath('sale.online_merchant_deduction', 4000)->assertJsonPath('sale.change_amount', 0);
    $sale = PosSale::query()->where('invoice_number', $response->json('sale.invoice_number'))->firstOrFail();
    expect($sale->online_merchant)->toBe($merchant)->and($inventory->fresh()->stock)->toBe(9);
    $this->get(route('sales'))->assertOk()->assertSee($label);
})->with([['shopeefood', 'ShopeeFood'], ['grabfood', 'GrabFood'], ['gofood', 'GoFood by Gojek'], ['tiktokfood', 'TiktokFood']]);

test('disabled or invalid online checkout has no sale or stock mutation', function () {
    [$inventory, $payload] = onlineMerchantFixture($this);
    $this->postJson(route('pos.checkout'), [...$payload, 'sales_channel' => 'online', 'online_merchant' => 'grabfood'])->assertUnprocessable();
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson(route('pos.checkout'), [...$payload, 'sales_channel' => 'online'])->assertUnprocessable()->assertJsonValidationErrors('online_merchant');
    $this->postJson(route('pos.checkout'), [...$payload, 'online_merchant' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('online_merchant');
    expect(PosSale::query()->count())->toBe(0)->and($inventory->fresh()->stock)->toBe(10);
    $this->postJson(route('pos.checkout'), $payload)->assertOk()->assertJsonPath('sale.online_merchant', null);
});

test('corrections preserve historical merchants when disabled and reject adding or changing them', function () {
    [$inventory, $payload] = onlineMerchantFixture($this, 'admin');
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson(route('pos.checkout'), [...$payload, 'online_merchant' => 'grabfood', 'paid_amount' => 16000])->assertOk();
    $sale = PosSale::query()->firstOrFail();
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => false]);
    $edit = [...$payload, 'paid_amount' => 16000, 'reason' => 'Koreksi jumlah', 'items' => [['id' => 'product-ONLINE-COFFEE', 'quantity' => 2]]];
    $this->putJson(route('sales.update', $sale), $edit)->assertOk();
    expect($sale->fresh()->online_merchant)->toBe('grabfood');
    $this->putJson(route('sales.update', $sale), [...$edit, 'online_merchant' => 'grabfood'])->assertOk();
    $this->putJson(route('sales.update', $sale), [...$edit, 'online_merchant' => 'gofood'])->assertUnprocessable();
    expect($sale->fresh()->online_merchant)->toBe('grabfood')->and($inventory->fresh()->stock)->toBe(8);
    $this->putJson(route('sales.update', $sale), [...$edit, 'online_merchant' => null])->assertOk();
    expect($sale->fresh()->online_merchant)->toBeNull();
    $this->putJson(route('sales.update', $sale), [...$edit, 'online_merchant' => 'grabfood'])->assertUnprocessable();
});

test('merchant checkout accepts net receipt without method and separates recap and report receipts', function () {
    [$inventory, $payload] = onlineMerchantFixture($this);
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    unset($payload['payment_method']);
    $this->postJson(route('pos.checkout'), [...$payload, 'sales_channel' => 'online', 'online_merchant' => 'gofood', 'paid_amount' => 15000])
        ->assertOk()->assertJsonPath('sale.payment_method', 'online_merchant')->assertJsonPath('sale.paid_amount', 15000);
    $sale = PosSale::query()->firstOrFail();
    $recap = app(CashierShiftSummaryService::class)->recap($sale->shift);
    expect($recap['onlineMerchantTotal'])->toBe(15000)->and($recap['onlineMerchantDeduction'])->toBe(5000)
        ->and($recap['cashTotal'])->toBe(0)->and($recap['qrisTotal'])->toBe(0)->and($recap['expectedCashInDrawer'])->toBe(0);
    $this->get(route('sales', ['payment_method' => 'online_merchant']))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['online_merchant_total'] === 15000);
    $this->get(route('reports'))->assertOk()->assertViewHas('cashierRevenues', fn ($rows) => $rows->first()['online_merchant_total'] === 15000);
    $lines = $this->get(route('reports.journal'))->assertOk()->viewData('lines');
    expect((int) $lines->where('debitAccount', 'Penerimaan Online Merchant')->sum('debit'))->toBe(15000)
        ->and((int) $lines->where('debitAccount', 'Potongan Online Merchant')->sum('debit'))->toBe(5000)
        ->and((int) $lines->sum('debit'))->toBe((int) $lines->sum('credit'));
});

test('merchant net must be explicitly supplied and cannot exceed the sale total', function ($amount) {
    [$inventory, $payload] = onlineMerchantFixture($this);
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $data = [...$payload, 'sales_channel' => 'online', 'online_merchant' => 'grabfood'];
    if ($amount !== 'missing') {
        $data['paid_amount'] = $amount;
    }
    $this->postJson(route('pos.checkout'), $data)->assertUnprocessable();
    expect(PosSale::query()->count())->toBe(0)->and($inventory->fresh()->stock)->toBe(10);
})->with(['missing', null, '', -1, 20001, 1.5]);

test('zero net receipt records full merchant deduction and ignores stale split payments', function () {
    [$inventory, $payload] = onlineMerchantFixture($this);
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson(route('pos.checkout'), [...$payload, 'payment_method' => 'split', 'payments' => [['method' => 'cash', 'amount' => 10000], ['method' => 'qris', 'amount' => 10000]], 'online_merchant' => 'shopeefood', 'paid_amount' => 0])
        ->assertOk()->assertJsonPath('sale.payment_method', 'online_merchant')->assertJsonPath('sale.online_merchant_deduction', 20000);
    expect(PosSale::query()->firstOrFail()->payments()->count())->toBe(0);
});

test('merchant corrections validate net and refresh deductions without adding cash', function () {
    [$inventory, $payload] = onlineMerchantFixture($this, 'admin');
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson(route('pos.checkout'), [...$payload, 'online_merchant' => 'grabfood', 'paid_amount' => 15000])->assertOk();
    $sale = PosSale::query()->firstOrFail();
    unset($payload['payment_method']);
    $this->putJson(route('sales.update', $sale), [...$payload, 'reason' => 'Koreksi penerimaan', 'paid_amount' => 12000])->assertOk();
    expect($sale->fresh()->paid_amount)->toBe(12000)->and($sale->fresh()->payment_method)->toBe('online_merchant');
    $this->putJson(route('sales.update', $sale), [...$payload, 'reason' => 'Nominal berlebih', 'paid_amount' => 21000])->assertUnprocessable();
    expect($sale->fresh()->paid_amount)->toBe(12000)->and($inventory->fresh()->stock)->toBe(9);
});

test('merchant deductions and sales discounts stay separate in financial profit and journal', function () {
    onlineMerchantFixture($this);
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson(route('pos.checkout'), ['items' => [['id' => 'product-ONLINE-COFFEE', 'quantity' => 1]], 'online_merchant' => 'gofood', 'discount' => 2000, 'paid_amount' => 15000])->assertOk();
    $lines = $this->get(route('reports.journal'))->assertOk()->viewData('lines');
    expect((int) $lines->where('debitAccount', 'Diskon Penjualan')->sum('debit'))->toBe(2000)
        ->and((int) $lines->where('debitAccount', 'Potongan Online Merchant')->sum('debit'))->toBe(3000)
        ->and((int) $lines->where('creditAccount', 'Pendapatan Online Merchant')->sum('credit'))->toBe(20000)
        ->and((int) $lines->sum('debit'))->toBe((int) $lines->sum('credit'));
    $controller = app(ReportController::class);
    $data = (new ReflectionMethod($controller, 'financialData'))->invoke($controller, Request::create('/'));
    expect($data['summary']['revenue'])->toBe(18000)->and($data['summary']['merchantDeduction'])->toBe(3000)
        ->and($data['summary']['netProfit'])->toBe(10000);
    $reportSummary = $this->get(route('reports'))->assertOk()->viewData('summary');
    expect($reportSummary['online_merchant_deduction'])->toBe(3000);
    expect((int) $reportSummary['total_expense'])->toBe(3000);
    $workbook = ExcelExport::read($this->get(route('reports.excel')));
    expect($workbook->getActiveSheet()->getCell('E2')->getValue())->toBe(15000);
    expect($workbook->getActiveSheet()->getCell('F2')->getValue())->toContain('Online Merchant Rp15.000', 'Potongan Online Merchant Rp3.000');
    $workbook->disconnectWorksheets();
    $this->get(route('reports.profit-loss.download'))->assertOk();
});

test('historical merchant payments retain original classification and void removes net from recap', function () {
    [$inventory, $payload] = onlineMerchantFixture($this);
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson(route('pos.checkout'), [...$payload, 'online_merchant' => 'gofood', 'paid_amount' => 15000])->assertOk();
    $sale = PosSale::query()->firstOrFail();
    $legacy = PosSale::query()->create([
        'cashier_shift_id' => $sale->cashier_shift_id, 'branch_id' => $sale->branch_id, 'user_id' => $sale->user_id,
        'invoice_number' => 'LEGACY-MERCHANT', 'online_merchant' => 'grabfood', 'payment_method' => 'qris',
        'subtotal' => 20000, 'discount' => 0, 'total' => 20000, 'paid_amount' => 20000, 'sold_at' => now(),
    ]);
    expect($legacy->online_merchant_deduction)->toBe(0);
    $summary = app(CashierShiftSummaryService::class);
    $recap = $summary->recap($summary->refresh($sale->shift));
    expect($recap['qrisTotal'])->toBe(20000)->and($recap['onlineMerchantTotal'])->toBe(15000);
    $this->postJson(route('sales.void', $sale), ['reason' => 'Duplikat merchant'])->assertOk();
    $recap = $summary->recap($sale->shift->fresh());
    expect($recap['onlineMerchantTotal'])->toBe(0)->and($recap['onlineMerchantDeduction'])->toBe(0)
        ->and($recap['qrisTotal'])->toBe(20000)->and($inventory->fresh()->stock)->toBe(10);
});

test('merchant payment cannot be forged without merchant and correction cannot cross branches', function () {
    [$inventory, $payload] = onlineMerchantFixture($this, 'admin');
    StoreSetting::current()->update(['cashier_online_merchant_enabled' => true]);
    $this->postJson(route('pos.checkout'), [...$payload, 'payment_method' => 'online_merchant', 'paid_amount' => 10000])->assertUnprocessable();
    $this->postJson(route('pos.checkout'), [...$payload, 'online_merchant' => 'gofood', 'paid_amount' => 15000])->assertOk();
    $sale = PosSale::query()->firstOrFail();
    $branch = Branch::query()->create(['name' => 'Cabang lain', 'code' => 'NET-OTHER', 'is_active' => true]);
    app(BranchContext::class)->setActive($branch->id);
    $this->putJson(route('sales.update', $sale), [...$payload, 'reason' => 'Cabang salah', 'paid_amount' => 10000])->assertNotFound();
    expect($sale->fresh()->paid_amount)->toBe(15000)->and($inventory->fresh()->stock)->toBe(9);
});

test('unrelated legacy merchant corrections preserve qris and split classifications until explicitly converted', function (string $method) {
    [$inventory, $payload] = onlineMerchantFixture($this, 'admin');
    $this->postJson(route('pos.checkout'), $payload)->assertOk();
    $sale = PosSale::query()->firstOrFail();
    $sale->update(['online_merchant' => 'grabfood', 'payment_method' => $method]);
    if ($method === 'split') {
        $sale->payments()->createMany([
            ['payment_method' => 'cash', 'amount' => 5000],
            ['payment_method' => 'qris', 'amount' => 15000],
        ]);
    }
    $edit = [...$payload, 'payment_method' => $method, 'online_merchant' => 'grabfood', 'buyer_nationality' => 'local', 'reason' => 'Koreksi kewarganegaraan'];
    if ($method === 'split') {
        $edit['payments'] = [['method' => 'cash', 'amount' => 5000], ['method' => 'qris', 'amount' => 15000]];
    }
    $this->putJson(route('sales.update', $sale), $edit)->assertOk();
    expect($sale->fresh()->payment_method)->toBe($method)->and($sale->payments()->count())->toBe($method === 'split' ? 2 : 0);
    $this->putJson(route('sales.update', $sale), [...$edit, 'sales_channel' => 'online', 'paid_amount' => 14000])->assertOk();
    expect($sale->fresh()->payment_method)->toBe('online_merchant')->and($sale->fresh()->paid_amount)->toBe(14000)->and($sale->payments()->count())->toBe(0);
})->with(['qris', 'split']);
