<?php

namespace App\Outreach\Voice;

use App\Outreach\OutreachConfig;

class VoiceFactory
{
    public function make(OutreachConfig $config): VoiceTelephony
    {
        return match ($config->get('voice.provider', 'twilio')) {
            'twilio' => new TwilioVoice($config),
            default => throw new \RuntimeException('Unknown voice provider: '.$config->get('voice.provider')),
        };
    }
}
