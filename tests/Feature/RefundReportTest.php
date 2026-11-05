<?php

use App\Http\Controllers\ReportController;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\SaleRefund;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\Support\ExcelExport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($this->owner);
    $this->branch = app(BranchContext::class)->active();
    $this->shift = CashierShift::query()->create(['user_id' => $this->owner->id, 'branch_id' => $this->branch->id, 'opened_at' => now()]);
    $this->product = Product::query()->create(['user_id' => $this->owner->id, 'sku' => 'REFUND-REPORT', 'name' => 'Coffee', 'sell_price' => 20000, 'buy_price' => 5000, 'stock' => 10, 'min_stock' => 0]);
    $this->sale = PosSale::query()->create([
        'user_id' => $this->owner->id, 'branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id,
        'invoice_number' => 'ORIGINAL-REPORT', 'payment_method' => 'cash', 'subtotal' => 40000,
        'discount' => 4000, 'total' => 36000, 'paid_amount' => 40000,
        'sold_at' => Carbon::parse('2026-11-01 12:00:00', 'Asia/Makassar')->utc(),
    ]);
    $this->saleItem = $this->sale->items()->create(['product_id' => $this->product->id, 'sku' => $this->product->sku, 'name' => 'Coffee', 'item_type' => 'product', 'quantity' => 2, 'unit_price' => 20000, 'line_total' => 40000]);
    $this->refund = SaleRefund::query()->create([
        'tenant_id' => $this->owner->id, 'branch_id' => $this->branch->id, 'pos_sale_id' => $this->sale->id,
        'user_id' => $this->owner->id, 'client_refund_id' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64),
        'refund_number' => 'RET-REPORT', 'amount' => 18000, 'refund_method' => 'cash', 'reason' => '=Returned cup',
        'refunded_at' => Carbon::parse('2026-11-02 00:15:00', 'Asia/Makassar')->utc(),
    ]);
    $this->refund->items()->create(['pos_sale_item_id' => $this->saleItem->id, 'quantity' => 1, 'amount' => 18000, 'restock' => true]);
});

test('reports account for cross-day refunds without rewriting original sales and discounts', function () {
    $this->get(route('reports', ['date_from' => '2026-11-02', 'date_to' => '2026-11-02']))->assertOk()
        ->assertViewHas('summary', fn ($summary) => $summary['refund_total'] === 18000 && $summary['net_after_refunds'] === -18000 && $summary['net_profit_estimate'] === -13000)
        ->assertSee('Retur Penjualan')->assertSee('RET-REPORT');
    $this->get(route('reports', ['date_from' => '2026-11-01', 'date_to' => '2026-11-01']))->assertOk()
        ->assertViewHas('summary', fn ($summary) => $summary['pos_revenue'] === 36000 && $summary['refund_total'] === 0);
    expect($this->sale->fresh()->subtotal)->toBe(40000)->and($this->sale->fresh()->discount)->toBe(4000)->and($this->sale->fresh()->total)->toBe(36000);
});

test('refund reports exclude other branches tenants and dates', function () {
    $otherBranch = Branch::query()->create(['user_id' => $this->owner->id, 'code' => 'REF-OTHER', 'name' => 'Other', 'is_active' => true]);
    $otherOwner = User::factory()->create(['role' => 'owner']);
    foreach ([['branch_id' => $otherBranch->id], ['tenant_id' => $otherOwner->id], ['refunded_at' => Carbon::parse('2026-11-03 00:00:00', 'Asia/Makassar')->utc()]] as $change) {
        $copy = $this->refund->replicate()->fill($change);
        $copy->refund_number = 'RET-'.Str::random(8);
        $copy->client_refund_id = (string) Str::uuid();
        $copy->save();
    }
    $this->get(route('reports', ['date_from' => '2026-11-02', 'date_to' => '2026-11-02']))->assertOk()
        ->assertViewHas('summary', fn ($summary) => $summary['refund_total'] === 18000)
        ->assertViewHas('refunds', fn ($refunds) => $refunds->count() === 1);
});

test('refund journal credits the manual payout account and recovers only explicitly restocked cost', function () {
    $response = $this->get(route('reports.journal', ['date_from' => '2026-11-02', 'date_to' => '2026-11-02']))->assertOk();
    $lines = collect($response->viewData('lines'));
    expect($lines->where('debitAccount', 'Retur Penjualan')->sum('debit'))->toBe(18000);
    expect($lines->where('creditAccount', 'Kas')->sum('credit'))->toBe(18000);
    expect($lines->where('debitAccount', 'Persediaan')->sum('debit'))->toBe(5000);
    expect($response->viewData('totalDebit'))->toBe($response->viewData('totalCredit'));
    $this->refund->items()->update(['restock' => false]);
    $this->get(route('reports.journal', ['date_from' => '2026-11-02', 'date_to' => '2026-11-02']))->assertOk()
        ->assertViewHas('lines', fn ($lines) => collect($lines)->where('debitAccount', 'Persediaan')->isEmpty());
});

test('financial profit subtracts refunds and recovers only restocked cost', function () {
    $method = new ReflectionMethod(ReportController::class, 'financialData');
    $data = $method->invoke(app(ReportController::class), Request::create('/', 'GET', ['date_from' => '2026-11-02', 'date_to' => '2026-11-02']));
    expect($data['summary']['refund_total'])->toBe(18000)->and($data['summary']['net_after_refunds'])->toBe(-18000)
        ->and($data['summary']['refund_cost_recovery'])->toBe(5000)->and($data['summary']['netProfit'])->toBe(-13000);
});

test('Excel appends safe refund detail and total without altering original sales columns', function () {
    $book = ExcelExport::read($this->get(route('reports.excel', ['date_from' => '2026-11-01', 'date_to' => '2026-11-02'])));
    expect($book->getSheetCount())->toBe(3);
    $sales = $book->getSheetByName('Penjualan');
    expect($sales->getCell('C2')->getValue())->toBe(40000)->and($sales->getCell('D2')->getValue())->toBe(4000)->and($sales->getCell('E2')->getValue())->toBe(36000);
    $refunds = $book->getSheetByName('Retur');
    expect($refunds->getCell('A2')->getValue())->toBe('RET-REPORT')->and($refunds->getCell('B2')->getValue())->toBe('ORIGINAL-REPORT')
        ->and($refunds->getCell('D2')->getValue())->toBe(18000)->and($refunds->getCell('D3')->getValue())->toBe(18000)
        ->and($refunds->getCell('F2')->getDataType())->toBe(DataType::TYPE_STRING);
    $book->disconnectWorksheets();
});
