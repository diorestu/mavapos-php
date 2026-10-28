<?php

use App\Http\Controllers\ReportController;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('journal records gross sales and separate discount debits', function (string $method, int $discount, array $payments, array $expectedDebits) {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $shift = CashierShift::query()->create([
        'user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()->subHour(),
    ]);

    foreach (['JOURNAL-001', 'JOURNAL-002'] as $invoice) {
        $sale = PosSale::query()->create([
            'cashier_shift_id' => $shift->id, 'branch_id' => $branch->id, 'user_id' => $owner->id,
            'invoice_number' => $invoice, 'payment_method' => $method,
            'subtotal' => 50000, 'discount' => $discount, 'total' => 50000 - $discount,
            'sold_at' => now(),
        ]);
        $sale->payments()->createMany($payments);
    }

    $query = ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()];
    $response = $this->get(route('reports.journal', $query))->assertOk();
    $lines = $response->viewData('lines');

    expect((int) $lines->where('creditAccount', 'Penjualan')->sum('credit'))->toBe(100000)
        ->and((int) $lines->sum('debit'))->toBe(100000)
        ->and((int) $lines->sum('credit'))->toBe(100000);
    foreach ($expectedDebits as $account => $amount) {
        expect((int) $lines->where('debitAccount', $account)->sum('debit'))->toBe($amount);
    }

    $controller = app(ReportController::class);
    $data = (new ReflectionMethod($controller, 'financialData'))->invoke($controller, Request::create('/', 'GET', $query));
    $summary = collect($data['journalLines']);
    expect((int) $summary->where('creditAccount', 'Penjualan')->sum('credit'))->toBe(100000)
        ->and((int) $summary->sum('debit'))->toBe(100000)
        ->and((int) $summary->sum('credit'))->toBe(100000)
        ->and($data['summary']['revenue'])->toBe(100000 - 2 * $discount);

    $document = new DOMDocument;
    @$document->loadHTML(view('pages.reports.financial-pdf', $data)->render());
    $xpath = new DOMXPath($document);
    foreach ($expectedDebits as $account => $amount) {
        $row = $xpath->query('//tbody/tr[td[4]="'.$account.'"]')->item(0);
        expect($row)->not->toBeNull();
        expect($row->childNodes->item(4)->textContent)->toBe('Rp'.number_format($amount, 0, ',', '.'));
        if ($discount > 0) {
            expect($row->childNodes->item(6)->textContent)->toBe('Rp0');
        }
    }
})->with([
    'cash discount' => ['cash', 5000, [], ['Kas' => 90000, 'Diskon Penjualan' => 10000]],
    'split discount' => ['split', 5000, [
        ['payment_method' => 'cash', 'amount' => 20000],
        ['payment_method' => 'qris', 'amount' => 25000],
    ], ['Kas' => 40000, 'Bank / QRIS' => 50000, 'Diskon Penjualan' => 10000]],
    'fully discounted' => ['free', 50000, [], ['Diskon Penjualan' => 100000]],
    'regular sale' => ['card', 0, [], ['Bank / Kartu' => 100000]],
]);
