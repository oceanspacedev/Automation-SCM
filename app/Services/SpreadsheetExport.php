<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel download of a master data list: bold grey header, frozen header row and auto-sized columns.
 */
class SpreadsheetExport
{
    /**
     * A cell showing a short label that opens the link, so long URLs don't clutter the sheet.
     *
     * @return array{label: string, url: string}
     */
    public static function link(string $url, string $label): array
    {
        return ['label' => $label, 'url' => $url];
    }

    /**
     * Text is written as text so codes and numbers like kode BT, NPWP and "0812…" keep their leading zeros.
     * A value made with link() is written as a clickable label.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function download(string $filename, string $sheetTitle, array $headers, iterable $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);
        $sheet->fromArray([$headers]);

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $header = $sheet->getStyle("A1:{$lastColumn}1");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');
        $header->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $rowNumber = 2;
        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $value) {
                $cell = $sheet->getCell([$index + 1, $rowNumber]);
                if (is_array($value) && isset($value['url'], $value['label'])) {
                    $cell->setValueExplicit($value['label'], DataType::TYPE_STRING);
                    $cell->getHyperlink()->setUrl($value['url']);
                    $cell->getStyle()->getFont()->setUnderline(Font::UNDERLINE_SINGLE)->getColor()->setARGB('FF2563EB');
                } elseif (is_int($value) || is_float($value)) {
                    $cell->setValue($value);
                } elseif ($value !== null && $value !== '') {
                    $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                }
            }
            $rowNumber++;
        }

        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(fn () => $writer->save('php://output'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
