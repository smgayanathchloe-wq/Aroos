<?php

namespace App\Jobs;

use App\Outreach\OutreachDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunOutreach implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // a send is never retried blindly; the row records the failure

    public function __construct(public string $kind, public int $id)
    {
    }

    public function handle(OutreachDispatcher $dispatcher): void
    {
        $this->kind === 'job' ? $dispatcher->runJob($this->id) : $dispatcher->runFollowUp($this->id);
    }
}
