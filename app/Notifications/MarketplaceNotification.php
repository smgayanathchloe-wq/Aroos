<?php

namespace App\Notifications;

use App\Http\Controllers\AdminController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification type for every marketplace event. In-app (database) is
 * written immediately; email and SMS go through the queue so a slow mail
 * server never blocks a request.
 */
class MarketplaceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Events that are also emailed / sent by SMS. */
    private const MAIL = ['enquiry.new', 'quote.received', 'quote.counter', 'booking.confirmed', 'booking.cancelled', 'enquiry.declined', 'provider.verified', 'provider.rejected', 'provider.banned'];
    private const SMS = ['booking.confirmed', 'booking.cancelled'];

    public function __construct(
        public string $event,
        public string $title,
        public string $body,
        public string $url,
        public string $key,
    ) {
    }

    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            'database',
            in_array($this->event, self::MAIL, true) && ! empty($notifiable->email) ? 'mail' : null,
            in_array($this->event, self::SMS, true) && ! empty($notifiable->phone) ? SmsChannel::class : null,
        ]));
    }

    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'key' => $this->key];
    }

    public function toMail(object $notifiable): MailMessage
    {
        AdminController::applyMailConfig();

        return (new MailMessage)->subject($this->title)->line($this->body)->action('Open in Aroos', $this->url);
    }

    public function toSms(object $notifiable): string
    {
        return $this->title.': '.$this->body;
    }
}
