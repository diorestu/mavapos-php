<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelExport
{
    public static function read(TestResponse $response): Spreadsheet
    {
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $file = tempnam(sys_get_temp_dir(), 'sales-xlsx-');
        try {
            file_put_contents($file, $response->streamedContent());

            return IOFactory::load($file);
        } finally {
            unlink($file);
        }
    }
}
