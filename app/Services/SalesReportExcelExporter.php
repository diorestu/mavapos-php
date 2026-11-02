<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportExcelExporter
{
    public function download(iterable $transactions, string $fileName, string $branchName): StreamedResponse
    {
        $workbook = new Spreadsheet;
        $workbook->getProperties()->setTitle('Laporan Penjualan')->setSubject($branchName);
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Penjualan');
        $sheet->fromArray([[
            'Nomor Transaksi', 'Tanggal Transaksi', 'Jumlah Nominal Penjualan',
            'Diskon Penjualan', 'Jumlah Nominal Diterima (Real)', 'Keterangan Penjualan',
            'Metode Pembayaran', 'Online Merchant',
        ]]);

        $row = 2;
        $dailyTotals = [];
        foreach ($transactions as $transaction) {
            $sheet->setCellValueExplicit('A'.$row, $transaction['invoice_number'], DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$row, Date::PHPToExcel($transaction['sold_at']));
            $sheet->setCellValue('C'.$row, $transaction['subtotal']);
            $sheet->setCellValue('D'.$row, $transaction['discount']);
            $sheet->setCellValue('E'.$row, $transaction['received_amount']);
            $sheet->setCellValueExplicit('F'.$row, $transaction['description'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('G'.$row, $transaction['payment_method'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H'.$row, $transaction['online_merchant'], DataType::TYPE_STRING);
            $date = $transaction['sold_at']->toDateString();
            $method = collect(explode(' + ', $transaction['payment_method']))
                ->sortBy(fn (string $label) => array_search($label, ['Tunai', 'QRIS', 'Kartu', 'Online Merchant', 'Gratis']))
                ->implode(' + ');
            $key = $date.'|'.$method;
            $dailyTotals[$key] ??= [
                'date' => $transaction['sold_at']->copy()->startOfDay(),
                'method' => $method,
                'count' => 0, 'sales' => 0, 'discount' => 0, 'received' => 0,
            ];
            $dailyTotals[$key]['count']++;
            $dailyTotals[$key]['sales'] += $transaction['subtotal'];
            $dailyTotals[$key]['discount'] += $transaction['discount'];
            $dailyTotals[$key]['received'] += $transaction['received_amount'];
            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle('B2:B'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm:ss');
            $sheet->getStyle('C2:E'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('F2:F'.$lastRow)->getAlignment()->setWrapText(true);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB');
        $sheet->getStyle('A1:H1')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(32);
        foreach (['A' => 26, 'B' => 24, 'C' => 25, 'D' => 22, 'E' => 30, 'F' => 70, 'G' => 30, 'H' => 20] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H'.$lastRow);

        $summary = $workbook->createSheet();
        $summary->setTitle('Ringkasan Harian');
        $summary->fromArray([[
            'Tanggal', 'Metode Pembayaran', 'Jumlah Transaksi', 'Total Penjualan', 'Total Diskon', 'Total Diterima (Real)',
        ]]);
        ksort($dailyTotals);
        $summaryRow = 2;
        foreach ($dailyTotals as $daily) {
            $summary->setCellValue('A'.$summaryRow, Date::PHPToExcel($daily['date']));
            $summary->setCellValueExplicit('B'.$summaryRow, $daily['method'], DataType::TYPE_STRING);
            $summary->setCellValue('C'.$summaryRow, $daily['count']);
            $summary->setCellValue('D'.$summaryRow, $daily['sales']);
            $summary->setCellValue('E'.$summaryRow, $daily['discount']);
            $summary->setCellValue('F'.$summaryRow, $daily['received']);
            $summaryRow++;
        }
        $summary->setAutoFilter('A1:F'.($summaryRow - 1));
        if ($summaryRow > 2) {
            $summary->getStyle('A2:A'.($summaryRow - 1))->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $summary->fromArray([[
                'Total', '', array_sum(array_column($dailyTotals, 'count')),
                array_sum(array_column($dailyTotals, 'sales')),
                array_sum(array_column($dailyTotals, 'discount')),
                array_sum(array_column($dailyTotals, 'received')),
            ]], null, 'A'.$summaryRow, true);
            $summary->getStyle('A'.$summaryRow.':F'.$summaryRow)->getFont()->setBold(true);
            $summary->getStyle('C2:F'.$summaryRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        $summary->getStyle('A1:F1')->getFont()->setBold(true);
        $summary->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB');
        $summary->getStyle('A1:F1')->getAlignment()->setWrapText(true);
        $summary->getRowDimension(1)->setRowHeight(32);
        foreach (['A' => 16, 'B' => 30, 'C' => 20, 'D' => 25, 'E' => 22, 'F' => 28] as $column => $width) {
            $summary->getColumnDimension($column)->setWidth($width);
        }
        $summary->freezePane('A2');
        $workbook->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($workbook): void {
            try {
                (new Xlsx($workbook))->save('php://output');
            } finally {
                $workbook->disconnectWorksheets();
            }
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
