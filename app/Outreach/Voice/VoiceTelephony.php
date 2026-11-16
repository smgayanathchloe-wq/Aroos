<?php

namespace App\Outreach\Voice;

/**
 * Carries the phone call. The agent itself (turn handling, prompts, the
 * Swahili model) runs in this app; the telephony provider only dials, turns the
 * guest's speech into text, and plays our replies.
 */
interface VoiceTelephony
{
    /** @return string provider call id */
    public function dial(string $to, string $from, string $answerUrl, string $statusUrl, int $timeLimitSec): string;

    /** Speak, then listen for the guest's reply and post it to $actionUrl. */
    public function speakAndListen(string $text, string $actionUrl): string;

    /** Speak, then hang up. */
    public function speakAndHangUp(string $text): string;
}
