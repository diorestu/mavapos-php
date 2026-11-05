<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Tests\Support\ExcelExport;

uses(RefreshDatabase::class);

test('sales Excel contains merchant details in ordered data columns even without transactions', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $response = $this->actingAs($owner)->get(route('reports.excel', ['date_from' => '2026-07-01', 'date_to' => '2026-07-20']));
    $workbook = ExcelExport::read($response);

    expect($response->headers->get('content-disposition'))->toContain('laporan-2026-07-01-sampai-2026-07-20.xlsx');
    expect($workbook->getSheetCount())->toBe(3);
    expect($workbook->getSheetByName('Ringkasan Harian')->toArray())->toBe([[
        'Tanggal', 'Metode Pembayaran', 'Jumlah Transaksi', 'Total Penjualan', 'Total Diskon', 'Total Diterima (Real)',
        'Online Merchant', 'Nama Merchant', 'Potongan Online Merchant',
    ]]);
    expect($workbook->getActiveSheet()->toArray())->toBe([[
        'Nomor Transaksi', 'Tanggal Transaksi', 'Jumlah Nominal Penjualan',
        'Diskon Penjualan', 'Jumlah Nominal Diterima (Real)', 'Keterangan Penjualan',
        'Metode Pembayaran', 'Online Merchant', 'Nama Merchant', 'Potongan Online Merchant',
    ]]);
    $workbook->disconnectWorksheets();
});

test('sales Excel exports gross discount and real receipts per transaction for the active branch in WITA', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $shift = CashierShift::query()->create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $defaults = ['cashier_shift_id' => $shift->id, 'user_id' => $owner->id, 'branch_id' => $branch->id, 'subtotal' => 100000, 'discount' => 20000, 'total' => 80000, 'sold_at' => Carbon::parse('2026-07-01 00:15:00', 'Asia/Makassar')->utc()];
    PosSale::query()->create($defaults + ['invoice_number' => '0000123', 'payment_method' => 'cash', 'paid_amount' => 100000, 'change_amount' => 20000]);
    PosSale::query()->create(array_replace($defaults, ['invoice_number' => 'MERCHANT', 'payment_method' => 'online_merchant', 'online_merchant' => 'grabfood', 'paid_amount' => 65000, 'sold_at' => Carbon::parse('2026-07-20 23:59:59', 'Asia/Makassar')->utc()]));
    $split = PosSale::query()->create($defaults + ['invoice_number' => 'SPLIT', 'payment_method' => 'split', 'paid_amount' => 80000]);
    $split->payments()->createMany([['payment_method' => 'cash', 'amount' => 50000], ['payment_method' => 'qris', 'amount' => 30000]]);
    $reverseSplit = PosSale::query()->create($defaults + ['invoice_number' => 'SPLIT-REVERSED', 'payment_method' => 'split', 'paid_amount' => 80000]);
    $reverseSplit->payments()->createMany([['payment_method' => 'qris', 'amount' => 30000], ['payment_method' => 'cash', 'amount' => 50000]]);
    PosSale::query()->create(array_replace($defaults, ['invoice_number' => '=1+1', 'payment_method' => 'free', 'total' => 0, 'discount' => 100000, 'complimentary_category' => 'influencer', 'complimentary_recipient_name' => '=HYPERLINK("https://example.com")']));
    PosSale::query()->create($defaults + ['invoice_number' => 'QRIS', 'payment_method' => 'qris', 'paid_amount' => 80000]);
    PosSale::query()->create($defaults + ['invoice_number' => 'CARD', 'payment_method' => 'card', 'paid_amount' => 80000]);

    PosSale::query()->create($defaults + ['invoice_number' => 'LEGACY-MERCHANT-QRIS', 'payment_method' => 'qris', 'online_merchant' => 'gofood', 'paid_amount' => 80000]);

    $otherBranch = Branch::query()->create(['name' => 'Kios Lain', 'code' => 'EXCEL-OTHER', 'user_id' => $owner->id, 'is_active' => true]);
    $otherOwner = User::factory()->create(['role' => 'owner']);
    $otherTenantBranch = Branch::query()->create(['name' => 'Tenant Lain', 'code' => 'EXCEL-TENANT', 'user_id' => $otherOwner->id, 'is_active' => true]);
    foreach ([
        ['invoice_number' => 'OTHER-BRANCH', 'branch_id' => $otherBranch->id],
        ['invoice_number' => 'OTHER-TENANT', 'branch_id' => $otherTenantBranch->id, 'user_id' => $otherOwner->id],
        ['invoice_number' => 'VOIDED', 'voided_at' => now()],
        ['invoice_number' => 'BEFORE', 'sold_at' => Carbon::parse('2026-06-30 23:59:59', 'Asia/Makassar')->utc()],
        ['invoice_number' => 'AFTER', 'sold_at' => Carbon::parse('2026-07-21 00:00:00', 'Asia/Makassar')->utc()],
    ] as $excluded) {
        PosSale::query()->create(array_replace($defaults, ['payment_method' => 'cash'], $excluded));
    }

    $workbook = ExcelExport::read($this->get(route('reports.excel', ['date_from' => '2026-07-01', 'date_to' => '2026-07-20'])));
    $sheet = $workbook->getActiveSheet();
    $rows = collect(array_slice($sheet->toArray(null, false, false), 1))->keyBy(fn ($row) => $row[0]);
    expect($rows)->toHaveCount(8);
    expect(array_slice($rows['0000123'], 6, 2))->toBe(['Tunai', 'Tidak']);
    expect(array_slice($rows['MERCHANT'], 6, 2))->toBe(['Online Merchant', 'Ya']);
    expect(array_slice($rows['SPLIT'], 6, 2))->toBe(['Tunai + QRIS', 'Tidak']);
    expect(array_slice($rows['QRIS'], 6, 2))->toBe(['QRIS', 'Tidak']);
    expect(array_slice($rows['CARD'], 6, 2))->toBe(['Kartu', 'Tidak']);
    expect(array_slice($rows['=1+1'], 6, 2))->toBe(['Gratis', 'Tidak']);
    expect(array_slice($rows['LEGACY-MERCHANT-QRIS'], 6, 2))->toBe(['QRIS', 'Ya']);
    expect(array_slice($rows['0000123'], 2, 3))->toBe([100000, 20000, 80000]);
    expect(array_slice($rows['MERCHANT'], 2, 3))->toBe([100000, 20000, 65000]);
    expect(array_slice($rows['SPLIT'], 2, 3))->toBe([100000, 20000, 80000]);
    expect(array_slice($rows['=1+1'], 2, 3))->toBe([100000, 100000, 0]);
    expect(array_slice($rows['MERCHANT'], 8))->toBe(['GrabFood', 15000]);
    expect(array_slice($rows['LEGACY-MERCHANT-QRIS'], 8))->toBe(['GoFood by Gojek', 0]);
    expect(array_slice($rows['0000123'], 8))->toBe(['-', 0]);
    expect($rows['QRIS'][4])->toBe(80000);
    expect($rows['CARD'][4])->toBe(80000);
    expect($rows['MERCHANT'][5])->toContain('GrabFood', '15.000');
    expect($rows['SPLIT'][5])->toContain('Tunai Rp50.000 + QRIS Rp30.000');
    expect($rows['=1+1'][5])->toContain('Gratis', 'Influencer');
    expect(Date::excelToDateTimeObject($rows['0000123'][1])->format('Y-m-d H:i:s'))->toBe('2026-07-01 00:15:00');
    expect(Date::excelToDateTimeObject($rows['MERCHANT'][1])->format('Y-m-d H:i:s'))->toBe('2026-07-20 23:59:59');
    for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
        expect($sheet->getCell('A'.$row)->getDataType())->toBe(DataType::TYPE_STRING);
        expect($sheet->getCell('B'.$row)->getDataType())->toBe(DataType::TYPE_NUMERIC);
        expect($sheet->getCell('F'.$row)->getDataType())->toBe(DataType::TYPE_STRING);
    }
    $summary = $workbook->getSheetByName('Ringkasan Harian');
    expect($summary)->not->toBeNull();
    $summaryRows = array_slice($summary->toArray(null, false, false), 1);
    $total = array_pop($summaryRows);
    expect($summaryRows)->toHaveCount(7);
    $grouped = collect($summaryRows)->keyBy(fn ($row) => Date::excelToDateTimeObject($row[0])->format('Y-m-d').'|'.$row[1].'|'.$row[7]);
    expect(array_slice($grouped['2026-07-01|QRIS|-'], 2, 4))->toBe([1, 100000, 20000, 80000]);
    expect(array_slice($grouped['2026-07-01|Tunai|-'], 2, 4))->toBe([1, 100000, 20000, 80000]);
    expect(array_slice($grouped['2026-07-01|Tunai + QRIS|-'], 2, 4))->toBe([2, 200000, 40000, 160000]);
    expect(array_slice($grouped['2026-07-01|Gratis|-'], 2, 4))->toBe([1, 100000, 100000, 0]);
    expect(array_slice($grouped['2026-07-20|Online Merchant|GrabFood'], 2, 4))->toBe([1, 100000, 20000, 65000]);
    expect(array_slice($grouped['2026-07-01|QRIS|GoFood by Gojek'], 2, 4))->toBe([1, 100000, 20000, 80000]);
    expect(array_slice($grouped['2026-07-20|Online Merchant|GrabFood'], 6))->toBe(['Ya', 'GrabFood', 15000]);
    expect(array_slice($total, 2, 4))->toBe([8, 800000, 240000, 545000]);
    expect($total[8])->toBe(15000);
    expect($summary->getAutoFilter()->getRange())->toBe('A1:I8');
    $workbook->disconnectWorksheets();
});

test('sales Excel rejects an invalid date range', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner)->getJson(route('reports.excel', ['date_from' => '2026-07-20', 'date_to' => '2026-07-01']))
        ->assertUnprocessable()->assertJsonValidationErrors('date_to');
});

test('sales Excel separates each merchant on the same day and includes zero real receipts', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $shift = CashierShift::query()->create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $merchants = [
        ['shopeefood', 'ShopeeFood', 16000, 4000],
        ['grabfood', 'GrabFood', 17000, 3000],
        ['gofood', 'GoFood by Gojek', 0, 20000],
        ['tiktokfood', 'TiktokFood', 18000, 2000],
    ];
    foreach ($merchants as [$code, $label, $received, $deduction]) {
        PosSale::query()->create([
            'user_id' => $owner->id, 'branch_id' => $branch->id, 'cashier_shift_id' => $shift->id,
            'invoice_number' => 'EXCEL-'.$code, 'payment_method' => 'online_merchant', 'online_merchant' => $code,
            'subtotal' => 20000, 'discount' => 0, 'total' => 20000, 'paid_amount' => $received,
            'sold_at' => Carbon::parse('2026-11-02 01:00:00', 'Asia/Makassar')->utc(),
        ]);
    }
    $workbook = ExcelExport::read($this->get(route('reports.excel', ['date_from' => '2026-11-02', 'date_to' => '2026-11-02'])));
    $details = collect(array_slice($workbook->getSheetByName('Penjualan')->toArray(null, false, false), 1))->keyBy(fn ($row) => $row[8]);
    $summary = array_slice($workbook->getSheetByName('Ringkasan Harian')->toArray(null, false, false), 1);
    $total = array_pop($summary);
    expect($summary)->toHaveCount(4);
    $summary = collect($summary)->keyBy(fn ($row) => $row[7]);
    foreach ($merchants as [$code, $label, $received, $deduction]) {
        expect($details[$label][4])->toBe($received);
        expect($details[$label][9])->toBe($deduction);
        expect($summary[$label][2])->toBe(1);
        expect($summary[$label][5])->toBe($received);
        expect($summary[$label][8])->toBe($deduction);
    }
    expect($total[2])->toBe(4);
    expect($total[5])->toBe(51000);
    expect($total[8])->toBe(29000);
    $workbook->disconnectWorksheets();
});
