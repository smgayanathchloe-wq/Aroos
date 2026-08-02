<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Guest;
use App\Models\Wedding;
use App\Models\Reminder;
use App\Models\ReminderLog;
use Illuminate\Support\Facades\Log;

class AppreciationSmsService
{
    /**
     * Send appreciation SMS after contribution
     */
    public static function sendAfterContribution(Contribution $contribution)
    {
        $wedding = $contribution->wedding;

        // Check if appreciation SMS is enabled
        if (!$wedding->send_appreciation_sms) {
            return;
        }

        // Get guest information
        $guest = $contribution->pledge->guest;
        if (!$guest || !$guest->phone) {
            return;
        }

        // Build message
        $template = $wedding->appreciation_sms_template ?? self::defaultTemplate();
        $message = self::interpolateTemplate($template, $guest, $wedding, $contribution);

        // Create reminder record
        $reminder = Reminder::create([
            'wedding_id' => $wedding->id,
            'name' => 'Appreciation SMS',
            'audience' => 'person',
            'channel' => 'sms',
            'body' => $message,
            'person_name' => $guest->fullName(),
            'person_phone' => $guest->phone,
            'person_email' => $guest->email,
        ]);

        // Send immediately
        try {
            ReminderService::sendToRecipient($reminder, $guest);
            ReminderLog::create([
                'reminder_id' => $reminder->id,
                'guest_id' => $guest->id,
                'channel' => 'sms',
                'status' => 'sent',
                'message_text' => $message,
            ]);
        } catch (\Exception $e) {
            Log::error("Appreciation SMS failed for guest {$guest->id}", [
                'error' => $e->getMessage(),
            ]);

            ReminderLog::create([
                'reminder_id' => $reminder->id,
                'guest_id' => $guest->id,
                'channel' => 'sms',
                'status' => 'failed',
                'message_text' => $message,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Default appreciation template
     */
    public static function defaultTemplate(): string
    {
        return "Asante {first_name}! Tunakushukuru kwa kuja kwenye harusi ya {couple_names}. Sisi ni bahati sana!";
    }

    /**
     * Interpolate template with variables
     */
    private static function interpolateTemplate(
        string $template,
        Guest $guest,
        Wedding $wedding,
        Contribution $contribution
    ): string {
        return str_replace([
            '{first_name}',
            '{last_name}',
            '{full_name}',
            '{couple_names}',
            '{amount}',
            '{currency}',
            '{date}',
        ], [
            $guest->first_name,
            $guest->last_name,
            $guest->fullName(),
            $wedding->coupleNames(),
            number_format($contribution->amount),
            $wedding->currency ?? 'TZS',
            now()->format('d-m-Y'),
        ], $template);
    }
}
