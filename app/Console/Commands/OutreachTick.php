<?php

namespace App\Console\Commands;

use App\Jobs\RunOutreach;
use App\Outreach\OutreachDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OutreachTick extends Command
{
    protected $signature = 'outreach:tick';

    protected $description = 'Queue AI pledge outreach contacts and follow-ups that are due';

    public function handle(OutreachDispatcher $dispatcher): int
    {
        $due = $dispatcher->due();
        foreach ($due['jobs'] as $id) {
            RunOutreach::dispatch('job', $id);
        }
        foreach ($due['follow_ups'] as $id) {
            RunOutreach::dispatch('follow_up', $id);
        }

        // Synthesised call audio is only needed while the call is live.
        $disk = Storage::disk('local');
        foreach ($disk->files('outreach-audio') as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }

        $this->info(count($due['jobs']).' job(s), '.count($due['follow_ups']).' follow-up(s) queued.');

        return self::SUCCESS;
    }
}
