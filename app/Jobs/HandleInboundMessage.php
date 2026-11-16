<?php

namespace App\Jobs;

use App\Models\Wedding;
use App\Outreach\Messaging\InboundMessage;
use App\Outreach\MessagingAgent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HandleInboundMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // the webhook receipt already makes delivery idempotent

    public function __construct(public int $weddingId, public InboundMessage $message)
    {
    }

    public function handle(MessagingAgent $agent): void
    {
        if ($wedding = Wedding::find($this->weddingId)) {
            $agent->handleInbound($wedding, $this->message);
        }
    }
}
