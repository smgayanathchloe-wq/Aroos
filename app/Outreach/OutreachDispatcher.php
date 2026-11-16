<?php

namespace App\Outreach;

use App\Models\FollowUp;
use App\Models\OutreachJob;
use App\Models\Pledge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Runs scheduled contacts (campaign jobs and automatic follow-ups) through the
 * contact policy and into the right channel agent. Each row is claimed
 * atomically, so overlapping scheduler runs or queue retries cannot send twice.
 */
class OutreachDispatcher
{
    public function __construct(
        private readonly ContactPolicy $policy,
        private readonly MessagingAgent $messaging,
        private readonly VoiceAgent $voice,
    ) {
    }

    public function runJob(int $jobId): void
    {
        if (! OutreachJob::whereKey($jobId)->where('status', 'pending')->update(['status' => 'running', 'updated_at' => now()])) {
            return;
        }
        $job = OutreachJob::with('pledge.wedding')->findOrFail($jobId);
        $job->increment('attempts');

        $this->contact($job->pledge, $job->channel, $job->purpose,
            onDeferred: fn (array $d) => $job->update(['status' => 'pending', 'scheduled_at' => $d['retry_at'], 'result' => 'deferred: '.$d['reason']]),
            onSkipped: fn (string $reason) => $job->update(['status' => 'skipped', 'result' => $reason]),
            onSent: fn ($conversation) => $job->update(['status' => 'done', 'conversation_id' => $conversation->id, 'result' => $conversation->status]),
            onFailed: fn (string $error) => $job->update(['status' => 'failed', 'result' => mb_substr($error, 0, 250)]),
        );
    }

    public function runFollowUp(int $followUpId): void
    {
        $followUp = FollowUp::with('pledge.wedding')->find($followUpId);
        if (! $followUp || ! $followUp->channel) {
            return; // committee tasks are not sent
        }
        if (! FollowUp::whereKey($followUpId)->where('status', 'pending')->update(['status' => 'running', 'updated_at' => now()])) {
            return;
        }

        if ($followUp->reason === 'promise_passed') {
            $followUp->pledge->promises()->where('status', 'open')
                ->whereDate('promised_date', '<', now()->toDateString())->update(['status' => 'lapsed']);
        }

        $this->contact($followUp->pledge, $followUp->channel, $followUp->reason,
            onDeferred: fn (array $d) => $followUp->update(['status' => 'pending', 'due_at' => $d['retry_at']]),
            onSkipped: fn (string $reason) => $followUp->update(['status' => 'skipped', 'notes' => trim($followUp->notes."\nSkipped: ".$reason)]),
            onSent: fn () => $followUp->update(['status' => 'done']),
            onFailed: fn (string $error) => $followUp->update(['status' => 'skipped', 'notes' => trim($followUp->notes."\nFailed: ".mb_substr($error, 0, 200))]),
        );
    }

    private function contact(Pledge $pledge, string $channel, string $purpose, callable $onDeferred, callable $onSkipped, callable $onSent, callable $onFailed): void
    {
        $denial = $this->policy->check($pledge, $channel, $purpose);
        if ($denial) {
            $denial['retry_at'] ? $onDeferred($denial) : $onSkipped($denial['reason']);

            return;
        }

        try {
            $conversation = $channel === 'voice'
                ? $this->voice->start($pledge, $purpose)
                : $this->messaging->start($pledge, $purpose, $channel);
            $conversation->status === 'failed' ? $onFailed((string) $conversation->summary) : $onSent($conversation);
        } catch (\Throwable $e) {
            Log::warning('Outreach contact failed', ['pledge' => $pledge->id, 'channel' => $channel, 'error' => $e->getMessage()]);
            $onFailed($e->getMessage());
        }
    }

    /** Ids that are due now; the scheduler queues one job per id. */
    public function due(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'jobs' => OutreachJob::where('status', 'pending')->where('scheduled_at', '<=', $now)->orderBy('scheduled_at')->limit(500)->pluck('id')->all(),
            'follow_ups' => FollowUp::where('status', 'pending')->whereNotNull('channel')->where('due_at', '<=', $now)->orderBy('due_at')->limit(500)->pluck('id')->all(),
        ];
    }
}
