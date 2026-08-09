<?php

namespace Tests\Support;

/**
 * Builds a minimal but genuine .xlsx file for tests.
 *
 * Writes the same zip-of-XML structure Excel produces, so the reader is
 * exercised against a real file rather than a mock of one.
 */
class XlsxBuilder
{
    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, true>  $dateColumns  Zero-based columns to mark as dates.
     */
    public static function create(string $path, array $rows, array $dateColumns = []): void
    {
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($rows, $dateColumns));

        $zip->close();
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    /** Style index 1 is a date format; index 0 is general. */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0"/>'
            .'<xf numFmtId="14" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, true>  $dateColumns
     */
    private static function sheet(array $rows, array $dateColumns): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xml .= '<row r="'.$rowNumber.'">';

            foreach ($row as $colIndex => $value) {
                $ref = self::columnLetter($colIndex).$rowNumber;

                if ($value === '' || $value === null) {
                    continue;
                }

                // Dates are written as serials with a date style, exactly as
                // Excel stores them.
                if (isset($dateColumns[$colIndex]) && $rowIndex > 0) {
                    $serial = self::dateToSerial($value);
                    $xml .= '<c r="'.$ref.'" s="1"><v>'.$serial.'</v></c>';

                    continue;
                }

                if (is_numeric($value) && $rowIndex > 0) {
                    $xml .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';

                    continue;
                }

                // Inline strings keep the fixture self-contained.
                $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'
                    .htmlspecialchars((string) $value, ENT_XML1)
                    .'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function dateToSerial(string $date): int
    {
        $epoch = mktime(0, 0, 0, 12, 30, 1899);
        $target = strtotime($date);

        return (int) round(($target - $epoch) / 86400);
    }

    private static function columnLetter(int $index): string
    {
        $letters = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letters = chr(65 + ($i % 26)).$letters;
        }

        return $letters;
    }
}
