<?php

namespace App\Services;

/**
 * Minimal .xlsx reader: converts the first worksheet to rows of strings.
 *
 * An .xlsx file is a zip of XML parts, so this needs only the zip and xml
 * extensions that PHP already ships. That avoids pulling in a spreadsheet
 * library for what is, for our purposes, a small read-only job.
 *
 * Deliberately supports only what an import needs: the first sheet, cell
 * values, shared strings, and dates. Formulas are read as their last cached
 * value, which is what a person sees in the file.
 */
class XlsxReader
{
    /**
     * @return array<int, array<int, string>> Rows of cell values.
     */
    public function toRows(string $path): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open the spreadsheet.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath = $this->firstSheetPath($zip);

            if ($sheetPath === null) {
                return [];
            }

            $xml = $zip->getFromName($sheetPath);

            if ($xml === false) {
                return [];
            }

            return $this->parseSheet($xml, $sharedStrings, $this->dateStyleIds($zip));
        } finally {
            $zip->close();
        }
    }

    /** Whether a file actually looks like an xlsx, whatever its extension. */
    public static function looksLikeXlsx(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = (string) fread($handle, 2);
        fclose($handle);

        // All zip archives, and therefore all xlsx files, start with "PK".
        return $magic === 'PK';
    }

    /**
     * Shared strings are held once in a lookup table and referenced by index,
     * which is how xlsx avoids repeating text.
     *
     * @return array<int, string>
     */
    private function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $doc = @simplexml_load_string($xml);

        if ($doc === false) {
            return [];
        }

        $strings = [];

        foreach ($doc->si as $item) {
            // Text may be a single <t>, or split across styled <r> runs that
            // must be concatenated to recover the original string.
            $text = '';

            if (isset($item->t)) {
                $text = (string) $item->t;
            } elseif (isset($item->r)) {
                foreach ($item->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /** Locate the first worksheet part inside the archive. */
    private function firstSheetPath(\ZipArchive $zip): ?string
    {
        // The workbook lists sheets in display order; the first is what a
        // person sees when they open the file.
        if (($workbook = $zip->getFromName('xl/workbook.xml')) !== false) {
            $doc = @simplexml_load_string($workbook);

            if ($doc !== false && isset($doc->sheets->sheet[0])) {
                $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
                $relId = (string) $doc->sheets->sheet[0]->attributes('r', true)->id;

                if ($rels !== false && $relId !== '') {
                    $relDoc = @simplexml_load_string($rels);

                    if ($relDoc !== false) {
                        foreach ($relDoc->Relationship as $rel) {
                            if ((string) $rel['Id'] === $relId) {
                                $target = ltrim((string) $rel['Target'], '/');

                                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                            }
                        }
                    }
                }
            }
        }

        // Fall back to the conventional location.
        return $zip->locateName('xl/worksheets/sheet1.xml') !== false
            ? 'xl/worksheets/sheet1.xml'
            : null;
    }

    /**
     * Style indexes whose number format is a date.
     *
     * Dates are stored as numbers, so without this a date column would import
     * as a meaningless serial like 45870.
     *
     * @return array<int, true>
     */
    private function dateStyleIds(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');

        if ($xml === false) {
            return [];
        }

        $doc = @simplexml_load_string($xml);

        if ($doc === false) {
            return [];
        }

        // Built-in numeric formats 14-22 and 45-47 are the date/time ones.
        $builtInDateFormats = array_merge(range(14, 22), range(45, 47));
        $customDateFormats = [];

        if (isset($doc->numFmts->numFmt)) {
            foreach ($doc->numFmts->numFmt as $fmt) {
                $code = (string) $fmt['formatCode'];

                // A custom format is a date if it contains date tokens and no
                // currency marker, e.g. "dd/mm/yyyy" but not "#,##0".
                if (preg_match('/[dmyhs]/i', $code) && ! str_contains($code, '#')) {
                    $customDateFormats[] = (int) $fmt['numFmtId'];
                }
            }
        }

        $dateFormats = array_merge($builtInDateFormats, $customDateFormats);
        $styles = [];

        if (isset($doc->cellXfs->xf)) {
            foreach ($doc->cellXfs->xf as $index => $xf) {
                if (in_array((int) $xf['numFmtId'], $dateFormats, true)) {
                    $styles[$index] = true;
                }
            }
        }

        return $styles;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<int, true>  $dateStyles
     * @return array<int, array<int, string>>
     */
    private function parseSheet(string $xml, array $sharedStrings, array $dateStyles): array
    {
        $doc = @simplexml_load_string($xml);

        if ($doc === false || ! isset($doc->sheetData)) {
            return [];
        }

        $rows = [];

        foreach ($doc->sheetData->row as $row) {
            $cells = [];
            $maxIndex = -1;

            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $index = $this->columnIndex($ref);
                $type = (string) $cell['t'];
                $styleId = isset($cell['s']) ? (int) $cell['s'] : null;

                $value = $this->cellValue($cell, $type, $sharedStrings);

                // Convert date serials to a readable date, matching what the
                // person sees rather than the underlying number.
                if ($value !== '' && $styleId !== null && isset($dateStyles[$styleId]) && is_numeric($value)) {
                    $value = $this->serialToDate((float) $value);
                }

                $cells[$index] = $value;
                $maxIndex = max($maxIndex, $index);
            }

            // Fill gaps so column positions line up with the header row even
            // when the file omits empty cells.
            $ordered = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $ordered[$i] = $cells[$i] ?? '';
            }

            $rows[] = $ordered;
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(\SimpleXMLElement $cell, string $type, array $sharedStrings): string
    {
        if ($type === 's') {
            $index = (int) $cell->v;

            return $sharedStrings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            if (isset($cell->is->t)) {
                return (string) $cell->is->t;
            }

            $text = '';
            if (isset($cell->is->r)) {
                foreach ($cell->is->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            return $text;
        }

        // For a formula cell, <v> holds the cached result, which is the value
        // the person sees in the spreadsheet.
        return isset($cell->v) ? (string) $cell->v : '';
    }

    /** Excel stores dates as days since 1899-12-30. */
    private function serialToDate(float $serial): string
    {
        $days = (int) floor($serial);

        return date('Y-m-d', mktime(0, 0, 0, 12, 30 + $days, 1899));
    }

    /** Turn a cell reference such as "AB7" into a zero-based column index. */
    private function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/', strtoupper($reference), $m)) {
            return 0;
        }

        $letters = $m[1];
        $index = 0;

        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
