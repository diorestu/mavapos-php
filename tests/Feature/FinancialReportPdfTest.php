<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Carbon;

test('financial PDF groups journal lines with the same date and accounts', function () {
    $controller = app(ReportController::class);
    $method = new ReflectionMethod($controller, 'summarizeJournalLines');
    $method->setAccessible(true);

    $lines = $method->invoke($controller, [
        ['date' => Carbon::parse('2026-07-27 09:00:00'), 'reference' => 'POS-001', 'description' => 'Penjualan POS', 'debitAccount' => 'Kas', 'creditAccount' => 'Penjualan', 'amount' => 200000, 'debit' => 200000, 'credit' => 200000],
        ['date' => Carbon::parse('2026-07-27 10:00:00'), 'reference' => 'POS-002', 'description' => 'Penjualan POS', 'debitAccount' => 'Kas', 'creditAccount' => 'Penjualan', 'amount' => 100000, 'debit' => 100000, 'credit' => 100000],
    ]);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]['amount'])->toBe(300000)
        ->and($lines[0]['reference'])->toBe('Ringkasan');
});
