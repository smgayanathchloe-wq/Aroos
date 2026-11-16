<?php

namespace App\Outreach\Messaging;

use App\Outreach\OutreachConfig;

class MessagingFactory
{
    /** The configured messaging provider, or the SMS path when $channel is 'sms'. */
    public function make(OutreachConfig $config, string $channel = 'whatsapp'): MessagingProvider
    {
        $provider = $config->get('messaging.provider');
        if ($channel === 'sms' && $provider !== 'twilio_whatsapp') {
            $provider = 'sms'; // Twilio accounts send SMS themselves; others use the platform gateway
        }

        return match ($provider) {
            'whatsapp_cloud' => new WhatsAppCloudProvider(
                $config->secret('whatsapp_token') ?: throw new MessagingException('Missing whatsapp_token.'),
                $config->get('messaging.phone_number_id') ?: throw new MessagingException('Missing messaging.phone_number_id.'),
                (bool) $config->get('messaging.interactive', true),
            ),
            'twilio_whatsapp' => new TwilioMessagingProvider(
                $config->secret('twilio_sid') ?: throw new MessagingException('Missing twilio_sid.'),
                $config->secret('twilio_token') ?: throw new MessagingException('Missing twilio_token.'),
                $config->get('branding.whatsapp_number') ?: throw new MessagingException('Missing branding.whatsapp_number.'),
                $channel === 'sms' ? 'sms' : 'whatsapp',
            ),
            'sms' => new SmsGatewayProvider(),
            default => throw new MessagingException('Unknown messaging provider: '.$provider),
        };
    }
}
