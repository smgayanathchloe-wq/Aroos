<?php

namespace App\Services;

use App\Models\Reminder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReminderService
{
    /**
     * Send a reminder to all matching recipients (guests or pledgers).
     * Returns [sentCount, failCount].
     */
    public static function send(Reminder $reminder): array
    {
        $wedding = $reminder->wedding;
        $sent = 0;
        $failed = 0;

        if ($reminder->stage === 'pledge_reminder') {
            $statuses = $reminder->target_pledge_statuses ?: ['pledged', 'partial'];
            $recipients = $wedding->pledges()->whereIn('status', $statuses)->get();

            foreach ($recipients as $pledge) {
                [$s, $f] = self::deliver($reminder, [
                    'name' => $pledge->pledger_name,
                    'email' => $pledge->pledger_email,
                    'phone' => $pledge->pledger_phone,
                    'guest_id' => $pledge->guest_id,
                    'pledge_id' => $pledge->id,
                    'placeholders' => [
                        '{name}' => $pledge->pledger_name,
                        '{couple}' => $wedding->coupleNames(),
                        '{date}' => optional($wedding->wedding_date)->format('F j, Y') ?? 'TBA',
                        '{venue}' => $wedding->venue_name ?? 'TBA',
                        '{amount}' => number_format((float) $pledge->amount, 2),
                        '{balance}' => number_format($pledge->balance(), 2),
                        '{currency}' => $wedding->currency,
                    ],
                ]);
                $sent += $s;
                $failed += $f;
            }
        } else {
            $statuses = $reminder->target_rsvp_statuses ?: ['pending', 'attending', 'maybe'];
            $recipients = $wedding->guests()->whereIn('rsvp_status', $statuses)->get();

            foreach ($recipients as $guest) {
                [$s, $f] = self::deliver($reminder, [
                    'name' => $guest->fullName(),
                    'email' => $guest->email,
                    'phone' => $guest->phone,
                    'guest_id' => $guest->id,
                    'pledge_id' => null,
                    'placeholders' => [
                        '{name}' => $guest->fullName(),
                        '{couple}' => $wedding->coupleNames(),
                        '{date}' => optional($wedding->wedding_date)->format('F j, Y') ?? 'TBA',
                        '{venue}' => $wedding->venue_name ?? 'TBA',
                        '{rsvp_link}' => route('public.rsvp', [$wedding->slug, $guest->invite_token]),
                        '{currency}' => $wedding->currency,
                    ],
                ]);
                $sent += $s;
                $failed += $f;
            }
        }

        $reminder->update(['status' => 'sent', 'sent_at' => now()]);

        return [$sent, $failed];
    }

    private static function deliver(Reminder $reminder, array $recipient): array
    {
        $sent = 0;
        $failed = 0;
        $body = strtr($reminder->body, $recipient['placeholders']);
        $subject = strtr($reminder->subject ?? 'A message about the wedding', $recipient['placeholders']);

        $channels = $reminder->channel === 'both' ? ['email', 'sms'] : [$reminder->channel];

        foreach ($channels as $channel) {
            $address = $channel === 'email' ? $recipient['email'] : $recipient['phone'];

            if (! $address) {
                continue;
            }

            try {
                if ($channel === 'email') {
                    Mail::raw($body, function ($message) use ($address, $subject) {
                        $message->to($address)->subject($subject);
                    });
                } else {
                    self::sendSms($address, $body);
                }

                $reminder->logs()->create([
                    'guest_id' => $recipient['guest_id'],
                    'pledge_id' => $recipient['pledge_id'],
                    'channel' => $channel,
                    'recipient' => $address,
                    'status' => 'sent',
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Reminder delivery failed', ['error' => $e->getMessage()]);
                $reminder->logs()->create([
                    'guest_id' => $recipient['guest_id'],
                    'pledge_id' => $recipient['pledge_id'],
                    'channel' => $channel,
                    'recipient' => $address,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [$sent, $failed];
    }

    private static function sendSms(string $phone, string $body): void
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (! $sid || ! $token || ! $from) {
            // No SMS gateway configured: log-only mode so flows remain testable.
            Log::info('SMS (log-only, configure services.twilio to send)', ['to' => $phone, 'body' => $body]);

            return;
        }

        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $phone,
                'Body' => $body,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Twilio error: '.$response->body());
        }
    }
}
