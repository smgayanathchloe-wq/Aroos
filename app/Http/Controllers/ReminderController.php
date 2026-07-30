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

        return view('reminders.index', compact('wedding', 'reminders'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $data = $this->validated($request);
        $wedding->reminders()->create($data);

        return back()->with('success', 'Reminder created.');
    }

    public function update(Request $request, Wedding $wedding, Reminder $reminder)
    {
        abort_unless($reminder->wedding_id === $wedding->id, 404);
        $reminder->update($this->validated($request));

        return back()->with('success', 'Reminder updated.');
    }

    public function destroy(Wedding $wedding, Reminder $reminder)
    {
        abort_unless($reminder->wedding_id === $wedding->id, 404);
        $reminder->delete();

        return back()->with('success', 'Reminder deleted.');
    }

    public function send(Wedding $wedding, Reminder $reminder)
    {
        abort_unless($reminder->wedding_id === $wedding->id, 404);

        [$sent, $failed] = ReminderService::send($reminder);

        $msg = "Reminder sent to {$sent} recipient(s).";
        if ($failed > 0) {
            $msg .= " {$failed} failed.";
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
            'stage' => ['required', 'in:save_the_date,invitation,rsvp_followup,pledge_reminder,contribution_thanks,week_before,day_before,custom'],
            'channel' => ['required', 'in:email,sms,both'],
            'target_rsvp_statuses' => ['nullable', 'array'],
            'target_rsvp_statuses.*' => ['in:pending,attending,declined,maybe'],
            'target_pledge_statuses' => ['nullable', 'array'],
            'target_pledge_statuses.*' => ['in:pledged,partial,fulfilled'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $data['status'] = ! empty($data['scheduled_at']) ? 'scheduled' : 'draft';

        return $data;
    }
}
