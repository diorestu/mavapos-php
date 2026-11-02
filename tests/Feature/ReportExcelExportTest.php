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

test('sales Excel contains only six ordered data columns even without transactions', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $response = $this->actingAs($owner)->get(route('reports.excel', ['date_from' => '2026-07-01', 'date_to' => '2026-07-20']));
    $workbook = ExcelExport::read($response);

    expect($response->headers->get('content-disposition'))->toContain('laporan-2026-07-01-sampai-2026-07-20.xlsx');
    expect($workbook->getSheetCount())->toBe(1);
    expect($workbook->getActiveSheet()->toArray())->toBe([[
        'Nomor Transaksi', 'Tanggal Transaksi', 'Jumlah Nominal Penjualan',
        'Diskon Penjualan', 'Jumlah Nominal Diterima (Real)', 'Keterangan Penjualan',
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
    PosSale::query()->create(array_replace($defaults, ['invoice_number' => '=1+1', 'payment_method' => 'free', 'total' => 0, 'discount' => 100000, 'complimentary_category' => 'influencer', 'complimentary_recipient_name' => '=HYPERLINK("https://example.com")']));
    PosSale::query()->create($defaults + ['invoice_number' => 'QRIS', 'payment_method' => 'qris', 'paid_amount' => 80000]);
    PosSale::query()->create($defaults + ['invoice_number' => 'CARD', 'payment_method' => 'card', 'paid_amount' => 80000]);

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
    expect($rows)->toHaveCount(6);
    expect(array_slice($rows['0000123'], 2, 3))->toBe([100000, 20000, 80000]);
    expect(array_slice($rows['MERCHANT'], 2, 3))->toBe([100000, 20000, 65000]);
    expect(array_slice($rows['SPLIT'], 2, 3))->toBe([100000, 20000, 80000]);
    expect(array_slice($rows['=1+1'], 2, 3))->toBe([100000, 100000, 0]);
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
    $workbook->disconnectWorksheets();
});

test('sales Excel rejects an invalid date range', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner)->getJson(route('reports.excel', ['date_from' => '2026-07-20', 'date_to' => '2026-07-01']))
        ->assertUnprocessable()->assertJsonValidationErrors('date_to');
});
