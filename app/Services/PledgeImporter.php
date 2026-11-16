<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Guest;
use App\Models\Pledge;
use App\Models\Wedding;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports pledges and contributions from a spreadsheet export.
 *
 * Expected columns (header names are matched loosely, see $aliases):
 *   name         - pledger's full name          (required)
 *   pledge       - what was pledged / notes     (optional)
 *   email        - pledger email                (optional)
 *   phone        - pledger phone                (optional)
 *   amount       - amount pledged               (optional, defaults to 0)
 *   date         - date of the pledge/payment   (optional)
 *   contribution - amount actually paid so far  (optional)
 *
 * A row becomes one Pledge, plus one Contribution when a contribution amount
 * is present. Re-importing the same file updates the existing records instead
 * of duplicating them (see matchKey()).
 */
class PledgeImporter
{
    /**
     * Header aliases. Keys are canonical field names, values are the
     * accepted header spellings for them. This lets people import their own
     * exports without renaming columns first.
     *
     * Values must be written in *normalized* form: lower-cased, with
     * underscores and hyphens already collapsed to single spaces, matching
     * what mapHeaders() produces. So "E-Mail" is listed as "e mail".
     */
    private const ALIASES = [
        'name' => ['name', 'full name', 'fullname', 'pledger', 'pledger name', 'contributor', 'contributor name', 'jina'],
        'pledge' => ['pledge', 'pledge type', 'item', 'description', 'ahadi'],
        'email' => ['email', 'e mail', 'email address', 'barua pepe'],
        'phone' => ['phone', 'phone number', 'mobile', 'tel', 'telephone', 'simu'],
        'amount' => ['amount', 'pledged', 'pledge amount', 'amount pledged', 'kiasi'],
        'date' => ['date', 'pledge date', 'received on', 'received', 'tarehe'],
        'contribution' => ['contribution', 'contributed', 'paid', 'amount paid', 'payment', 'mchango'],
    ];

    /** Rows we refuse to process in a single upload, as a safety valve. */
    private const MAX_ROWS = 5000;

    public function __construct(private Wedding $wedding) {}

    /**
     * Parse a spreadsheet (.csv, .tsv or .xlsx) into normalized rows without
     * touching the database.
     *
     * @return array{rows: array<int, array<string, mixed>>, headers: array<string, string|null>, errors: array<int, string>}
     */
    public function preview(string $path): array
    {
        try {
            $raw = XlsxReader::looksLikeXlsx($path)
                ? (new XlsxReader)->toRows($path)
                : $this->readDelimited($path);
        } catch (\Throwable $e) {
            return ['rows' => [], 'headers' => [], 'errors' => ['Could not read the file: '.$e->getMessage()]];
        }

        if ($raw === []) {
            return ['rows' => [], 'headers' => [], 'errors' => ['The file appears to be empty.']];
        }

        $headerRow = array_shift($raw);
        $map = $this->mapHeaders($headerRow);

        $rows = [];
        $errors = [];
        $lineNumber = 1;

        foreach ($raw as $line) {
            $lineNumber++;

            if (count($rows) >= self::MAX_ROWS) {
                $errors[] = 'Stopped at '.self::MAX_ROWS.' rows. Split the file and import the rest separately.';
                break;
            }

            // Skip blank lines, which trailing newlines and spare spreadsheet
            // rows both produce.
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = $this->normalizeRow($line, $map, $lineNumber);

            if ($row['name'] === '') {
                $errors[] = "Row {$lineNumber}: skipped, no name.";

                continue;
            }

            $rows[] = $row;
        }

        [$rows, $mergeNotes] = $this->consolidateDuplicates($rows);

        return ['rows' => $rows, 'headers' => $map, 'errors' => array_merge($errors, $mergeNotes)];
    }

    /**
     * Combine rows that refer to the same person within one file.
     *
     * A sheet may list someone several times, typically one row per
     * instalment. Each row would otherwise be matched to the same pledge and
     * overwrite the previous one, so only the final row would survive and the
     * earlier payments would vanish silently. Contributions are summed and the
     * largest pledged figure is kept, since that is the total promised rather
     * than a per-instalment amount.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function consolidateDuplicates(array $rows): array
    {
        $merged = [];
        $notes = [];
        $counts = [];

        foreach ($rows as $row) {
            $key = $this->identityKey($row);

            if (! isset($merged[$key])) {
                $merged[$key] = $row;
                $counts[$key] = 1;

                continue;
            }

            $counts[$key]++;
            $existing = $merged[$key];

            // Payments accumulate; the pledged figure is a total, not a sum.
            $existing['contribution'] += $row['contribution'];
            $existing['amount'] = max($existing['amount'], $row['amount']);

            // Keep whichever details the later row actually supplied.
            foreach (['email', 'phone', 'pledge'] as $field) {
                if ($existing[$field] === '' && $row[$field] !== '') {
                    $existing[$field] = $row[$field];
                }
            }

            $existing['date'] = $row['date'] ?? $existing['date'];

            $merged[$key] = $existing;
        }

        foreach ($counts as $key => $count) {
            if ($count > 1) {
                $notes[] = sprintf(
                    '%s appeared on %d rows; their contributions were added together (%s).',
                    $merged[$key]['name'],
                    $count,
                    number_format($merged[$key]['contribution'], 2)
                );
            }
        }

        return [array_values($merged), $notes];
    }

    /**
     * Identity used to spot the same person twice in one file.
     *
     * Mirrors findExistingPledge(): email, then phone, then name.
     */
    private function identityKey(array $row): string
    {
        if ($row['email'] !== '') {
            return 'e:'.Str::lower($row['email']);
        }

        if ($row['phone'] !== '') {
            return 'p:'.$row['phone'];
        }

        return 'n:'.Str::lower(trim($row['name']));
    }

    /**
     * Read a delimited text file into raw rows.
     *
     * @return array<int, array<int, string>>
     */
    private function readDelimited(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('could not open the uploaded file');
        }

        // Strip a UTF-8 BOM if Excel added one, otherwise the first header
        // never matches its alias and the whole file looks unmapped.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $delimiter = $this->detectDelimiter($path);
        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($line === [null]) {
                continue;
            }

            $rows[] = array_map(fn ($v) => (string) $v, $line);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Import the given rows, inserting new records and updating existing ones.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, contributions: int, skipped: int}
     */
    public function import(array $rows): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'contributions' => 0, 'skipped' => 0];

        // One transaction so a mid-file failure cannot leave the books
        // half-updated, which would be very hard to reconcile by hand.
        DB::transaction(function () use ($rows, &$stats) {
            foreach ($rows as $row) {
                if ($row['name'] === '') {
                    $stats['skipped']++;

                    continue;
                }

                $pledge = $this->findExistingPledge($row);

                $attributes = [
                    'pledger_name' => $row['name'],
                    'pledger_email' => $row['email'] ?: null,
                    'pledger_phone' => $row['phone'] ?: null,
                    'amount' => $row['amount'],
                    'due_date' => $row['date'],
                    'notes' => $row['pledge'] ?: null,
                ];

                if ($pledge) {
                    // Only overwrite fields the file actually supplied, so a
                    // sparse re-import cannot blank out good existing data.
                    $pledge->fill(array_filter(
                        $attributes,
                        fn ($v) => $v !== null && $v !== '' && $v !== 0.0
                    ));

                    // If the amount came from the pledge column, an earlier
                    // import may have stored that same figure as a note. Clear
                    // it, or the row keeps a duplicate of its own amount as
                    // stray text that no re-import would ever tidy up.
                    if ($row['pledge'] === '' && $row['amount'] > 0 && $this->noteIsJustTheAmount($pledge)) {
                        $pledge->notes = null;
                    }

                    $pledge->save();
                    $stats['updated']++;
                } else {
                    $pledge = $this->wedding->pledges()->create($attributes + [
                        'guest_id' => $this->matchGuestId($row),
                        'status' => 'pledged',
                    ]);
                    $stats['created']++;
                }

                if ($row['contribution'] > 0) {
                    if ($this->syncContribution($pledge, $row)) {
                        $stats['contributions']++;
                    }
                }

                $pledge->refreshStatus();
            }
        });

        return $stats;
    }

    /**
     * Find a pledge this row should update rather than duplicate.
     *
     * Matching is deliberately conservative and ordered by how strong the
     * signal is: email, then phone, then an exact name match. Email and phone
     * are near-unique per person; name alone is the weakest, so it is only
     * used when the row carries no contact details at all.
     */
    private function findExistingPledge(array $row): ?Pledge
    {
        $query = $this->wedding->pledges();

        if ($row['email'] !== '') {
            $match = (clone $query)->whereRaw('LOWER(pledger_email) = ?', [Str::lower($row['email'])])->first();
            if ($match) {
                return $match;
            }
        }

        if ($row['phone'] !== '') {
            $match = (clone $query)->where('pledger_phone', $row['phone'])->first();
            if ($match) {
                return $match;
            }
        }

        if ($row['email'] === '' && $row['phone'] === '') {
            return (clone $query)->whereRaw('LOWER(pledger_name) = ?', [Str::lower($row['name'])])->first();
        }

        return null;
    }

    /**
     * Record the contribution for a pledge, updating rather than stacking.
     *
     * The file gives a running total per person, not a ledger of individual
     * payments. Blindly inserting on every import would inflate the collected
     * figure, so we keep a single import-owned contribution row per pledge and
     * adjust its amount. Contributions entered by hand in the UI are left
     * alone, identified by the reference marker.
     */
    private function syncContribution(Pledge $pledge, array $row): bool
    {
        $existing = Contribution::where('pledge_id', $pledge->id)
            ->where('reference', self::importReference())
            ->first();

        if ($existing) {
            if ((float) $existing->amount === $row['contribution']) {
                return false;
            }

            $existing->update([
                'amount' => $row['contribution'],
                'received_on' => $row['date'] ?? $existing->received_on,
            ]);

            return true;
        }

        $this->wedding->contributions()->create([
            'pledge_id' => $pledge->id,
            'guest_id' => $pledge->guest_id,
            'contributor_name' => $row['name'],
            'amount' => $row['contribution'],
            'method' => 'other',
            'source' => 'import',
            'reference' => self::importReference(),
            'received_on' => $row['date'] ?? now()->toDateString(),
            'notes' => 'Imported from spreadsheet.',
        ]);

        return true;
    }

    /**
     * Whether a pledge's note is nothing but a bare number.
     *
     * Used to clean up rows left by an earlier import that filed the pledged
     * figure as a note. Deliberately strict: only a note that is purely
     * numeric qualifies, so a real remark is never discarded.
     */
    private function noteIsJustTheAmount(Pledge $pledge): bool
    {
        $notes = trim((string) $pledge->notes);

        if ($notes === '') {
            return false;
        }

        return preg_match('/^[0-9][0-9.,\s]*$/', $notes) === 1;
    }

    /** Marker that identifies contributions this importer owns. */
    public static function importReference(): string
    {
        return 'spreadsheet-import';
    }

    /** Link the pledge to an existing guest when contact details line up. */
    private function matchGuestId(array $row): ?int
    {
        if ($row['email'] !== '') {
            $guest = Guest::where('wedding_id', $this->wedding->id)
                ->whereRaw('LOWER(email) = ?', [Str::lower($row['email'])])
                ->first();
            if ($guest) {
                return $guest->id;
            }
        }

        if ($row['phone'] !== '') {
            $guest = Guest::where('wedding_id', $this->wedding->id)
                ->where('phone', $row['phone'])
                ->first();
            if ($guest) {
                return $guest->id;
            }
        }

        return null;
    }

    /** Turn a raw CSV line into canonical, typed fields. */
    private function normalizeRow(array $raw, array $map, int $lineNumber): array
    {
        $get = function (string $field) use ($raw, $map): string {
            $index = $map[$field] ?? null;
            if ($index === null || ! array_key_exists($index, $raw)) {
                return '';
            }

            return trim((string) $raw[$index]);
        };

        $pledgeValue = $get('pledge');
        $amount = $this->parseMoney($get('amount'));

        // Many real sheets use the "pledge" column for the pledged *figure*
        // rather than a description ("Pledge: 500,000"). When there is no
        // separate amount, treat a numeric pledge column as the amount, or
        // the money is silently filed away as a text note and the pledge is
        // recorded as zero.
        $pledgeIsNumeric = $pledgeValue !== '' && $this->looksLikeMoney($pledgeValue);

        if ($amount === 0.0 && $pledgeIsNumeric) {
            $amount = $this->parseMoney($pledgeValue);
            $pledgeValue = '';
        }

        return [
            'line' => $lineNumber,
            'name' => $get('name'),
            'pledge' => $pledgeValue,
            'email' => filter_var($get('email'), FILTER_VALIDATE_EMAIL) ? $get('email') : '',
            'phone' => $this->normalizePhone($get('phone')),
            'amount' => $amount,
            'contribution' => $this->parseMoney($get('contribution')),
            'date' => $this->parseDate($get('date')),
        ];
    }

    /**
     * Whether a value is a bare monetary figure rather than prose.
     *
     * "500,000" and "TZS 1,500.50" qualify; "Cash gift" and "Sofa set x2" do
     * not. Requires at least one digit and no substantial run of letters, so
     * a description that merely mentions a number is not mistaken for one.
     */
    private function looksLikeMoney(string $value): bool
    {
        if (! preg_match('/\d/', $value)) {
            return false;
        }

        // Allow a short currency code (TZS, USD, /=) but reject real words.
        $letters = preg_replace('/[^a-z]/i', '', $value) ?? '';

        return strlen($letters) <= 3;
    }

    /**
     * Parse money written the many ways spreadsheets produce it:
     * "1,500.00", "TZS 1500", "1 500", "(500)" for negatives, "" for blank.
     */
    private function parseMoney(string $value): float
    {
        if ($value === '') {
            return 0.0;
        }

        $negative = str_contains($value, '(') && str_contains($value, ')');

        // Drop currency symbols, letters and spaces, keeping digits and separators.
        $clean = preg_replace('/[^0-9.,\-]/', '', $value) ?? '';

        // Treat a comma as a decimal separator only when it looks like one
        // (exactly two trailing digits and no dot present), otherwise it is
        // a thousands separator: "1,50" -> 1.50 but "1,500" -> 1500.
        if (! str_contains($clean, '.') && preg_match('/,\d{2}$/', $clean)) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        $number = (float) $clean;

        return $negative ? -abs($number) : $number;
    }

    /** Keep phone numbers as digits plus an optional leading +. */
    private function normalizePhone(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Spreadsheets often mangle numbers into 2.55712E+11; that has lost
        // precision already, so reject it rather than store a wrong number.
        if (stripos($value, 'e+') !== false) {
            return '';
        }

        $clean = preg_replace('/[^0-9+]/', '', $value) ?? '';

        return Str::startsWith($clean, '+')
            ? '+'.preg_replace('/[^0-9]/', '', substr($clean, 1))
            : preg_replace('/[^0-9]/', '', $clean);
    }

    /** Accept the common date spellings; return Y-m-d or null. */
    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        // Excel serial dates (days since 1899-12-30) arrive as bare integers.
        if (preg_match('/^\d{5}$/', $value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'd M Y', 'j F Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // Try the next format.
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Match the file's headers to our canonical fields.
     *
     * @return array<string, int|null>
     */
    private function mapHeaders(array $headerRow): array
    {
        $normalized = [];
        foreach ($headerRow as $index => $header) {
            $key = Str::lower(trim(str_replace(['_', '-'], ' ', (string) $header)));
            $key = preg_replace('/\s+/', ' ', $key) ?? $key;
            $normalized[$index] = $key;
        }

        $map = [];
        foreach (self::ALIASES as $field => $accepted) {
            $map[$field] = null;
            foreach ($normalized as $index => $header) {
                if (in_array($header, $accepted, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /** Guess the delimiter so tab- and semicolon-separated exports work too. */
    private function detectDelimiter(string $path): string
    {
        $line = (string) file_get_contents($path, false, null, 0, 4096);
        $firstLine = strtok($line, "\n") ?: '';

        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }
}
