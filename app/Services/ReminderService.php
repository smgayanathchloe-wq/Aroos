<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReminderService
{
    /**
     * Send a reminder to all recipients matching its audience.
     * Returns [sentCount, failCount].
     */
    public static function send(Reminder $reminder): array
    {
        $sent = 0;
        $failed = 0;

        foreach (self::recipients($reminder) as $recipient) {
            [$s, $f] = self::deliver($reminder, $recipient);
            $sent += $s;
            $failed += $f;
        }

        $reminder->update(['status' => 'sent', 'sent_at' => now()]);

        return [$sent, $failed];
    }

    /**
     * Resolve the recipient list for a reminder based on its audience.
     * Each recipient: [name, email, phone, guest_id, pledge_id, placeholders].
     */
    public static function recipients(Reminder $reminder): array
    {
        $wedding = $reminder->wedding;
        $base = [
            '{couple}' => $wedding->coupleNames(),
            '{date}' => optional($wedding->wedding_date)->format('F j, Y') ?? 'TBA',
            '{venue}' => $wedding->venue_name ?? 'TBA',
            '{currency}' => $wedding->currency,
        ];
        $filter = $reminder->audience_filter ?? [];
        $out = [];

        switch ($reminder->audience) {
            case 'pledgers':
                $statuses = $reminder->target_pledge_statuses ?: ['pledged', 'partial'];
                foreach ($wedding->pledges()->whereIn('status', $statuses)->get() as $pledge) {
                    $out[] = [
                        'name' => $pledge->pledger_name,
                        'email' => $pledge->pledger_email,
                        'phone' => $pledge->pledger_phone,
                        'guest_id' => $pledge->guest_id,
                        'pledge_id' => $pledge->id,
                        'placeholders' => $base + [
                            '{name}' => $pledge->pledger_name,
                            '{amount}' => number_format((float) $pledge->amount, 2),
                            '{balance}' => number_format($pledge->balance(), 2),
                        ],
                    ];
                }
                break;

            case 'vendors':
                $query = $wedding->vendors();
                if (! empty($filter['vendor_status'])) {
                    $query->where('status', $filter['vendor_status']);
                }
                foreach ($query->get() as $vendor) {
                    $out[] = [
                        'name' => $vendor->name,
                        'email' => $vendor->email,
                        'phone' => $vendor->phone,
                        'guest_id' => null,
                        'pledge_id' => null,
                        'placeholders' => $base + ['{name}' => $vendor->name],
                    ];
                }
                break;

            case 'crew':
                foreach ($wedding->crewTeams()->with('members')->get() as $team) {
                    foreach ($team->members as $member) {
                        $out[] = [
                            'name' => $member->name,
                            'email' => null,
                            'phone' => $member->phone,
                            'guest_id' => null,
                            'pledge_id' => null,
                            'placeholders' => $base + ['{name}' => $member->name, '{team}' => $team->name],
                        ];
                    }
                }
                break;

            case 'person':
                if ($reminder->person_name) {
                    $out[] = [
                        'name' => $reminder->person_name,
                        'email' => $reminder->person_email,
                        'phone' => $reminder->person_phone,
                        'guest_id' => null,
                        'pledge_id' => null,
                        'placeholders' => $base + ['{name}' => $reminder->person_name],
                    ];
                }
                break;

            default: // guests
                $query = $wedding->guests();
                $statuses = $reminder->target_rsvp_statuses ?: null;
                if ($statuses) {
                    $query->whereIn('rsvp_status', $statuses);
                }
                if (($filter['attendance'] ?? null) === 'checked_in') {
                    $query->whereNotNull('checked_in_at');
                } elseif (($filter['attendance'] ?? null) === 'not_checked_in') {
                    $query->whereNull('checked_in_at');
                }
                foreach ($query->get() as $guest) {
                    $out[] = [
                        'name' => $guest->fullName(),
                        'email' => $guest->email,
                        'phone' => $guest->phone,
                        'guest_id' => $guest->id,
                        'pledge_id' => null,
                        'placeholders' => $base + [
                            '{name}' => $guest->fullName(),
                            '{rsvp_link}' => route('public.rsvp', [$wedding->slug, $guest->invite_token]),
                            '{table}' => $guest->seatingTable?->name ?? 'TBA',
                        ],
                    ];
                }
        }

        return $out;
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

    /**
     * Send SMS through the gateway configured in admin settings.
     * Supported: beem (Tanzania), twilio, africastalking, none (log only).
     */
    public static function sendSms(string $phone, string $body): void
    {
        $driver = Setting::get('sms_driver', 'none');

        if ($driver === 'beem') {
            $key = Setting::get('beem_api_key');
            $secret = Setting::get('beem_secret_key');
            $sender = Setting::get('sms_sender_id', 'AROOS');

            if (! $key || ! $secret) {
                throw new \RuntimeException('Beem gateway not configured.');
            }

            $response = Http::withBasicAuth($key, $secret)
                ->post('https://apisms.beem.africa/v1/send', [
                    'source_addr' => $sender,
                    'schedule_time' => '',
                    'encoding' => 0,
                    'message' => $body,
                    'recipients' => [['recipient_id' => 1, 'dest_addr' => preg_replace('/[^0-9]/', '', $phone)]],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Beem error: '.$response->body());
            }

            return;
        }

        if ($driver === 'twilio') {
            $sid = Setting::get('twilio_sid');
            $token = Setting::get('twilio_token');
            $from = Setting::get('twilio_from');

            if (! $sid || ! $token || ! $from) {
                throw new \RuntimeException('Twilio gateway not configured.');
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

            return;
        }

        if ($driver === 'africastalking') {
            $username = Setting::get('at_username');
            $apiKey = Setting::get('at_api_key');
            $sender = Setting::get('sms_sender_id');

            if (! $username || ! $apiKey) {
                throw new \RuntimeException("Africa's Talking gateway not configured.");
            }

            $response = Http::asForm()
                ->withHeaders(['apiKey' => $apiKey, 'Accept' => 'application/json'])
                ->post('https://api.africastalking.com/version1/messaging', array_filter([
                    'username' => $username,
                    'to' => $phone,
                    'message' => $body,
                    'from' => $sender,
                ]));

            if ($response->failed()) {
                throw new \RuntimeException("Africa's Talking error: ".$response->body());
            }

            return;
        }

        // No gateway configured: log-only so flows stay testable.
        Log::info('SMS (log-only, configure a gateway in Admin → Gateways)', ['to' => $phone, 'body' => $body]);
    }
}
