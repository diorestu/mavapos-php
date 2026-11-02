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
        ]]);

        $row = 2;
        foreach ($transactions as $transaction) {
            $sheet->setCellValueExplicit('A'.$row, $transaction['invoice_number'], DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$row, Date::PHPToExcel($transaction['sold_at']));
            $sheet->setCellValue('C'.$row, $transaction['subtotal']);
            $sheet->setCellValue('D'.$row, $transaction['discount']);
            $sheet->setCellValue('E'.$row, $transaction['received_amount']);
            $sheet->setCellValueExplicit('F'.$row, $transaction['description'], DataType::TYPE_STRING);
            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle('B2:B'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm:ss');
            $sheet->getStyle('C2:E'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('F2:F'.$lastRow)->getAlignment()->setWrapText(true);
        }
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB');
        $sheet->getStyle('A1:F1')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(32);
        foreach (['A' => 26, 'B' => 24, 'C' => 25, 'D' => 22, 'E' => 30, 'F' => 70] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F'.$lastRow);

        return response()->streamDownload(function () use ($workbook): void {
            try {
                (new Xlsx($workbook))->save('php://output');
            } finally {
                $workbook->disconnectWorksheets();
            }
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
