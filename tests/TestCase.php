<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;

abstract class TestCase extends BaseTestCase
{
    /**
     * Save an Excel download to a temporary file and return its path.
     */
    protected function saveDownloadedSpreadsheet(TestResponse $response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        return $path;
    }

    /**
     * Rows of the first sheet of an Excel download, header row included.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function downloadedSpreadsheetRows(TestResponse $response): array
    {
        return IOFactory::load($this->saveDownloadedSpreadsheet($response))->getActiveSheet()->toArray(null, false, false, false);
    }
}
