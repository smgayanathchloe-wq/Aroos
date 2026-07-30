<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Wedding;
use App\Services\ReminderService;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Wedding $wedding)
    {
        $reminders = $wedding->reminders()->withCount('logs')->latest()->get();
        $wedding->load(['guests', 'vendors', 'crewTeams.members']);

        return view('reminders.index', compact('wedding', 'reminders'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $wedding->reminders()->create($this->validated($request));

        return back()->with('success', 'Message created. Review it below, then press Send.');
    }

    public function update(Request $request, Wedding $wedding, Reminder $reminder)
    {
        abort_unless($reminder->wedding_id === $wedding->id, 404);
        $reminder->update($this->validated($request));

        return back()->with('success', 'Message updated.');
    }

    public function destroy(Wedding $wedding, Reminder $reminder)
    {
        abort_unless($reminder->wedding_id === $wedding->id, 404);
        $reminder->delete();

        return back()->with('success', 'Message deleted.');
    }

    public function preview(Wedding $wedding, Reminder $reminder)
    {
        abort_unless($reminder->wedding_id === $wedding->id, 404);
        $recipients = ReminderService::recipients($reminder);

        return view('reminders.preview', compact('wedding', 'reminder', 'recipients'));
    }

    public function send(Wedding $wedding, Reminder $reminder)
    {
        abort_unless($reminder->wedding_id === $wedding->id, 404);

        [$sent, $failed] = ReminderService::send($reminder);

        $msg = "Message sent to {$sent} recipient(s).";
        if ($failed > 0) {
            $msg .= " {$failed} failed (see logs).";
        }

        return back()->with('success', $msg);
    }

    public function logs(Wedding $wedding, Reminder $reminder)
    {
        abort_unless($reminder->wedding_id === $wedding->id, 404);
        $logs = $reminder->logs()->with(['guest', 'pledge'])->latest()->get();

        return view('reminders.logs', compact('wedding', 'reminder', 'logs'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audience' => ['required', 'in:guests,pledgers,vendors,crew,person'],
            'stage' => ['nullable', 'string', 'max:50'],
            'channel' => ['required', 'in:email,sms,both'],
            'target_rsvp_statuses' => ['nullable', 'array'],
            'target_rsvp_statuses.*' => ['in:pending,attending,declined,maybe'],
            'target_pledge_statuses' => ['nullable', 'array'],
            'target_pledge_statuses.*' => ['in:pledged,partial,fulfilled'],
            'attendance' => ['nullable', 'in:any,checked_in,not_checked_in'],
            'vendor_status' => ['nullable', 'string', 'max:50'],
            'person_name' => ['nullable', 'string', 'max:255'],
            'person_email' => ['nullable', 'email', 'max:255'],
            'person_phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $filter = [];
        if (($data['attendance'] ?? 'any') !== 'any') {
            $filter['attendance'] = $data['attendance'];
        }
        if (! empty($data['vendor_status'])) {
            $filter['vendor_status'] = $data['vendor_status'];
        }

        unset($data['attendance'], $data['vendor_status']);
        $data['audience_filter'] = $filter ?: null;
        $data['stage'] = $data['stage'] ?? 'custom';
        $data['status'] = 'draft';

        return $data;
    }
}
