<?php

namespace App\Http\Controllers\Api;

use App\Models\Guest;
use App\Models\Reminder;
use App\Models\ReminderLog;
use App\Models\Setting;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reminders: the SMS and email the couple sends to guests and pledgers.
 *
 * Delivery goes through the same App\Services\ReminderService the web
 * dashboard uses, so a message sent from the phone takes the identical path
 * through the configured gateway (Beem, Twilio or Africa's Talking) and is
 * logged the same way.
 */
class ReminderController extends ApiController
{
    protected const CHANNELS = ['sms', 'email', 'both'];

    protected const AUDIENCES = ['guests', 'pledgers', 'person', 'crew', 'vendors'];

    public function index(Request $request): JsonResponse
    {
        $query = Reminder::query();
        $this->scoped($request, $query);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($this->perPage($request));

        return $this->paginated($paginator, fn ($r) => $this->transform($r));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $reminder = $this->findScoped($request, Reminder::class, $id);

        $payload = $this->transform($reminder);
        $payload['logs'] = $this->logsFor($reminder);

        return $this->ok($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $this->validated($request);
        $data['wedding_id'] = $wedding->id;
        $data['status'] = $data['status'] ?? 'draft';
        // `stage` is NOT NULL; the web controller defaults it the same way.
        $data['stage'] = $data['stage'] ?? 'custom';

        $reminder = Reminder::create($data);

        return $this->ok($this->transform($reminder->fresh()));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $reminder = $this->findScoped($request, Reminder::class, $id);

        // A message that has already gone out must not be rewritten after
        // the fact; its logs would no longer match what was delivered.
        if ($reminder->status === 'sent') {
            return $this->fail('This reminder has already been sent and can no longer be edited.', 422);
        }

        $reminder->fill($this->validated($request, true))->save();

        return $this->ok($this->transform($reminder->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $this->findScoped($request, Reminder::class, $id)->delete();

        return $this->ok(['deleted' => true]);
    }

    /**
     * Who would receive this, and what each person would actually read,
     * with placeholders resolved. Nothing is sent.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $reminder = $this->findScoped($request, Reminder::class, $id);

        $recipients = collect(ReminderService::recipients($reminder));

        return $this->ok([
            'reminder_id' => $reminder->id,
            'channel' => $reminder->channel,
            'total_recipients' => $recipients->count(),
            // Someone with no phone cannot receive an SMS, and vice versa.
            'reachable' => [
                'sms' => $recipients->filter(fn ($r) => ! empty($r['phone']))->count(),
                'email' => $recipients->filter(fn ($r) => ! empty($r['email']))->count(),
            ],
            'samples' => $recipients->take(5)->map(fn ($r) => [
                'name' => $r['name'],
                'email' => $r['email'],
                'phone' => $r['phone'],
                'body' => strtr($reminder->body, $r['placeholders']),
                'subject' => $reminder->subject
                    ? strtr($reminder->subject, $r['placeholders'])
                    : null,
            ])->values(),
        ]);
    }

    /**
     * Actually deliver the reminder, through the same service the web uses.
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $reminder = $this->findScoped($request, Reminder::class, $id);

        if ($reminder->status === 'sent') {
            return $this->fail('This reminder has already been sent.', 422);
        }

        [$sent, $failed] = ReminderService::send($reminder);

        return $this->ok([
            'reminder_id' => $reminder->id,
            'sent' => $sent,
            'failed' => $failed,
            'status' => $reminder->fresh()->status,
            'message' => $failed > 0
                ? "Sent {$sent}, {$failed} failed. Check the delivery log."
                : "Sent to {$sent} recipient(s).",
        ]);
    }

    /**
     * Delivery log, so the couple can see exactly who received what.
     */
    public function logs(Request $request, int $id): JsonResponse
    {
        $reminder = $this->findScoped($request, Reminder::class, $id);

        return $this->ok($this->logsFor($reminder));
    }

    /**
     * Send a one-off message to a single person without creating a campaign,
     * which is the common case from a phone.
     */
    public function quickSend(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $request->validate([
            'channel' => ['required', Rule::in(self::CHANNELS)],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40', 'required_if:channel,sms', 'required_if:channel,both'],
            'email' => ['nullable', 'email', 'max:255', 'required_if:channel,email', 'required_if:channel,both'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:1600'],
        ]);

        $reminder = Reminder::create([
            'wedding_id' => $wedding->id,
            'name' => 'Direct message to '.$data['name'],
            'audience' => 'person',
            'channel' => $data['channel'],
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'person_name' => $data['name'],
            'person_phone' => $data['phone'] ?? null,
            'person_email' => $data['email'] ?? null,
            'status' => 'draft',
            'stage' => 'custom',
        ]);

        [$sent, $failed] = ReminderService::send($reminder);

        return $this->ok([
            'reminder_id' => $reminder->id,
            'sent' => $sent,
            'failed' => $failed,
            'message' => $sent > 0 ? 'Message sent.' : 'Message could not be delivered.',
        ]);
    }

    /**
     * Which channels are actually usable, so the app can disable SMS when no
     * gateway is configured instead of failing at send time.
     */
    public function channels(Request $request): JsonResponse
    {
        $this->wedding($request);

        $driver = Setting::get('sms_driver', 'none');

        $configured = match ($driver) {
            'beem' => (bool) Setting::get('beem_api_key') && (bool) Setting::get('beem_secret_key'),
            'twilio' => (bool) Setting::get('twilio_sid') && (bool) Setting::get('twilio_token'),
            'africastalking' => (bool) Setting::get('at_api_key') && (bool) Setting::get('at_username'),
            default => false,
        };

        return $this->ok([
            'sms' => [
                'driver' => $driver,
                'configured' => $configured,
                'sender_id' => Setting::get('sms_sender_id'),
                // Without a gateway the service logs instead of sending, which
                // keeps flows testable but is not real delivery.
                'note' => $configured
                    ? null
                    : 'No SMS gateway configured; messages are written to the log only.',
            ],
            'email' => [
                'driver' => config('mail.default'),
                'configured' => config('mail.default') !== null,
                'from' => config('mail.from.address'),
            ],
        ]);
    }

    protected function logsFor(Reminder $reminder): array
    {
        $logs = ReminderLog::where('reminder_id', $reminder->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        // Resolve guest names in one query so a failed delivery can be
        // read as "Amina's number bounced" rather than a bare phone number.
        $names = Guest::whereIn('id', $logs->pluck('guest_id')->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($guest) => [$guest->id => $guest->fullName()]);

        return $logs
            ->map(fn ($log) => [
                'id' => $log->id,
                'channel' => $log->channel,
                'recipient' => $log->recipient ?? null,
                'recipient_name' => $log->guest_id ? ($names[$log->guest_id] ?? null) : null,
                'status' => $log->status,
                'error' => $log->error ?? $log->error_message ?? null,
                'guest_id' => $log->guest_id,
                'sent_at' => optional($log->created_at)->toIso8601String(),
            ])->values()->all();
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$rule, 'string', 'max:160'],
            'channel' => [$rule, Rule::in(self::CHANNELS)],
            'audience' => [$rule, Rule::in(self::AUDIENCES)],
            'body' => [$rule, 'string', 'max:1600'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:200'],
            'stage' => ['sometimes', 'nullable', 'string', 'max:60'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'recurrence' => ['sometimes', 'nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['draft', 'scheduled', 'sent'])],
            'target_rsvp_statuses' => ['sometimes', 'nullable', 'array'],
            'target_pledge_statuses' => ['sometimes', 'nullable', 'array'],
            'audience_filter' => ['sometimes', 'nullable', 'array'],
            'person_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'person_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'person_email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);
    }

    protected function transform(Reminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'name' => $reminder->name,
            'channel' => $reminder->channel,
            'audience' => $reminder->audience,
            'stage' => $reminder->stage,
            'subject' => $reminder->subject,
            'body' => $reminder->body,
            'status' => $reminder->status,
            'scheduled_at' => optional($reminder->scheduled_at)->toIso8601String(),
            'sent_at' => optional($reminder->sent_at)->toIso8601String(),
            'recurrence' => $reminder->recurrence,
            'person_name' => $reminder->person_name,
            'person_phone' => $reminder->person_phone,
            'person_email' => $reminder->person_email,
            'target_rsvp_statuses' => $reminder->target_rsvp_statuses,
            'target_pledge_statuses' => $reminder->target_pledge_statuses,
            'log_count' => $reminder->logs()->count(),
            'created_at' => optional($reminder->created_at)->toIso8601String(),
        ];
    }
}
