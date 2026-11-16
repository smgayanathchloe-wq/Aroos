<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Outreach\VoiceAgent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeCall implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $conversationId)
    {
    }

    public function handle(VoiceAgent $agent): void
    {
        if ($conversation = Conversation::find($this->conversationId)) {
            $agent->finalize($conversation);
        }
    }
}
