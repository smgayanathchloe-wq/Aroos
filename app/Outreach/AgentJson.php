<?php

namespace App\Outreach;

use Carbon\CarbonImmutable;

/**
 * Turns model text into validated structured data. Models sometimes wrap JSON
 * in prose or code fences, and may emit wrong types; nothing reaches the
 * database without passing through here.
 */
final class AgentJson
{
    public const CALL_OUTCOMES = ['promise', 'already_paid', 'no_pledge', 'callback', 'refused', 'opt_out', 'voicemail', 'no_answer', 'wrong_person', 'answered_question_only'];
    public const INTENTS = ['greeting', 'promise_date', 'pledge_amount', 'question', 'objection', 'already_paid', 'opt_out', 'thanks', 'other'];

    /** @throws \InvalidArgumentException when no JSON object is present */
    public static function decode(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new \InvalidArgumentException('No JSON object in model output.');
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($data)) {
            throw new \InvalidArgumentException('Model output is not valid JSON.');
        }

        return $data;
    }

    public static function voiceTurn(array $raw): array
    {
        return [
            'say' => is_string($raw['say'] ?? null) ? trim($raw['say']) : '',
            'end_call' => filter_var($raw['end_call'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    public static function callOutcome(array $raw): array
    {
        $outcome = in_array($raw['outcome'] ?? null, self::CALL_OUTCOMES, true) ? $raw['outcome'] : null;

        return [
            'outcome' => $outcome,
            'promised_date' => self::date($raw['promised_date'] ?? null),
            'promised_amount' => self::amount($raw['promised_amount'] ?? null),
            'pledged_amount' => self::amount($raw['pledged_amount'] ?? null),
            'confidence' => in_array($raw['confidence'] ?? null, ['high', 'medium', 'low'], true) ? $raw['confidence'] : 'low',
            'guest_language' => in_array($raw['guest_language'] ?? null, ['sw', 'en', 'other'], true) ? $raw['guest_language'] : null,
            'questions_asked' => self::strings($raw['questions_asked'] ?? []),
            'unanswered_questions' => self::strings($raw['unanswered_questions'] ?? []),
            'fallback_used' => filter_var($raw['fallback_used'] ?? false, FILTER_VALIDATE_BOOL),
            'summary' => is_string($raw['summary'] ?? null) ? mb_substr(trim($raw['summary']), 0, 1000) : null,
            'callback_time' => self::datetime($raw['callback_time'] ?? null),
        ];
    }

    public static function messagingReply(array $raw): array
    {
        return [
            'reply_text' => is_string($raw['reply_text'] ?? null) ? trim($raw['reply_text']) : '',
            'intent' => in_array($raw['intent'] ?? null, self::INTENTS, true) ? $raw['intent'] : 'other',
            'pledged_amount' => self::amount($raw['pledged_amount'] ?? null),
            'promised_date' => self::date($raw['promised_date'] ?? null),
            'question' => is_string($raw['question'] ?? null) && trim($raw['question']) !== '' ? trim($raw['question']) : null,
            'fallback_used' => filter_var($raw['fallback_used'] ?? false, FILTER_VALIDATE_BOOL),
            'next_follow_up_at' => self::datetime($raw['next_follow_up_at'] ?? null),
        ];
    }

    private static function date(mixed $v): ?string
    {
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $v));

        return checkdate($m, $d, $y) ? $v : null;
    }

    private static function datetime(mixed $v): ?string
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($v)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function amount(mixed $v): ?float
    {
        if (is_string($v)) {
            $v = str_replace([',', ' '], '', $v);
        }

        return is_numeric($v) && (float) $v > 0 ? round((float) $v, 2) : null;
    }

    private static function strings(mixed $v): array
    {
        return is_array($v) ? array_values(array_filter(array_map(fn ($s) => is_string($s) ? trim($s) : null, $v))) : [];
    }
}
