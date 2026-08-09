<?php

namespace App\Console\Commands;

use App\Models\Pledge;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repairs pledges imported before the importer understood that a numeric
 * "pledge" column holds the pledged figure rather than a description.
 *
 * Those rows were saved with amount = 0 and the real figure left in notes.
 * This moves the figure back into amount and clears the note.
 *
 * Runs as a dry run unless --commit is passed.
 */
class RepairPledgeAmounts extends Command
{
    protected $signature = 'pledges:repair-amounts
                            {--wedding= : Restrict to one wedding id}
                            {--commit : Actually write the changes}
                            {--restore= : Roll back using a backup file from storage/app/pledge-backups}';

    protected $description = 'Move pledged figures stranded in notes back into the amount column';

    public function handle(): int
    {
        if ($file = $this->option('restore')) {
            return $this->restore($file);
        }

        $query = Pledge::query()
            ->where('amount', 0)
            ->whereNotNull('notes');

        if ($weddingId = $this->option('wedding')) {
            $query->where('wedding_id', $weddingId);
        }

        $candidates = $query->get()->filter(
            fn (Pledge $p) => $this->looksLikeMoney((string) $p->notes)
        );

        if ($candidates->isEmpty()) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'wedding', 'name', 'amount now', 'notes', 'amount after'],
            $candidates->take(20)->map(fn (Pledge $p) => [
                $p->id,
                $p->wedding_id,
                $p->pledger_name,
                number_format((float) $p->amount, 2),
                $p->notes,
                number_format($this->parseMoney((string) $p->notes), 2),
            ])->all()
        );

        if ($candidates->count() > 20) {
            $this->line('... and '.($candidates->count() - 20).' more.');
        }

        if (! $this->option('commit')) {
            $this->warn($candidates->count().' pledge(s) would be repaired. Re-run with --commit to apply.');

            return self::SUCCESS;
        }

        // The repair clears notes, so the original figure only exists in this
        // snapshot afterwards. Written before any change so the operation can
        // be undone if the parsed amounts turn out to be wrong.
        $backupPath = $this->writeBackup($candidates);
        $this->line('Backup written to '.$backupPath);

        $repaired = 0;

        DB::transaction(function () use ($candidates, &$repaired) {
            foreach ($candidates as $pledge) {
                $pledge->amount = $this->parseMoney((string) $pledge->notes);
                $pledge->notes = null;
                $pledge->save();

                // The pledged figure changed, so fulfilled/partial/pledged
                // must be recomputed against what was actually contributed.
                $pledge->refreshStatus();
                $repaired++;
            }
        });

        $this->info("Repaired {$repaired} pledge(s).");
        $this->line('Undo with: php artisan pledges:repair-amounts --restore='.basename($backupPath));

        return self::SUCCESS;
    }

    /**
     * Snapshot the rows about to change, so the repair is reversible.
     *
     * @param  Collection<int, Pledge>  $candidates
     */
    private function writeBackup($candidates): string
    {
        $payload = $candidates->map(fn (Pledge $p) => [
            'id' => $p->id,
            'wedding_id' => $p->wedding_id,
            'pledger_name' => $p->pledger_name,
            'amount' => (string) $p->amount,
            'notes' => $p->notes,
            'status' => $p->status,
        ])->values()->all();

        $dir = storage_path('app/pledge-backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.'/repair-'.now()->format('Ymd-His').'.json';
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /** Roll the repair back from a snapshot. */
    private function restore(string $file): int
    {
        $path = str_contains($file, '/') ? $file : storage_path('app/pledge-backups/'.$file);

        if (! is_file($path)) {
            $this->error('Backup not found: '.$path);

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows) || $rows === []) {
            $this->error('Backup is empty or unreadable.');

            return self::FAILURE;
        }

        $restored = 0;

        DB::transaction(function () use ($rows, &$restored) {
            foreach ($rows as $row) {
                $pledge = Pledge::find($row['id']);
                if (! $pledge) {
                    continue;
                }

                $pledge->amount = $row['amount'];
                $pledge->notes = $row['notes'];
                $pledge->status = $row['status'];
                $pledge->save();
                $restored++;
            }
        });

        $this->info("Restored {$restored} pledge(s) from ".basename($path).'.');

        return self::SUCCESS;
    }

    private function looksLikeMoney(string $value): bool
    {
        if (! preg_match('/\d/', $value)) {
            return false;
        }

        $letters = preg_replace('/[^a-z]/i', '', $value) ?? '';

        return strlen($letters) <= 3;
    }

    private function parseMoney(string $value): float
    {
        $clean = preg_replace('/[^0-9.,\-]/', '', $value) ?? '';

        if (! str_contains($clean, '.') && preg_match('/,\d{2}$/', $clean)) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return (float) $clean;
    }
}
