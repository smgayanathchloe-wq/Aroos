<?php

namespace App\Console\Commands;

use App\Models\Pledge;
use Illuminate\Console\Command;
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
                            {--commit : Actually write the changes}';

    protected $description = 'Move pledged figures stranded in notes back into the amount column';

    public function handle(): int
    {
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
