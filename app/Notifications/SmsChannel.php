<?php

namespace App\Notifications;

use App\Services\ReminderService;
use Illuminate\Notifications\Notification;

/** Sends a notification's toSms() text through the platform SMS gateway. */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! empty($notifiable->phone) && method_exists($notification, 'toSms')) {
            ReminderService::sendSms($notifiable->phone, $notification->toSms($notifiable));
        }
    }
}
