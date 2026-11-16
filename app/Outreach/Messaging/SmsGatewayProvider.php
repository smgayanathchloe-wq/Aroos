<?php

namespace App\Outreach\Messaging;

use App\Services\ReminderService;

/** Outbound SMS through the platform gateway (Beem, Africa's Talking, Twilio). */
class SmsGatewayProvider implements MessagingProvider
{
    public function channel(): string { return 'sms'; }

    public function supportsInteractive(): bool { return false; }

    public function send(string $to, string $text, array $options = []): ?string
    {
        ReminderService::sendSms('+'.$to, NumberedOptions::append($text, $options));

        return null;
    }
}
