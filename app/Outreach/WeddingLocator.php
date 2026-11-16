<?php

namespace App\Outreach;

use App\Models\Wedding;

/** Finds the tenant an inbound webhook belongs to, from the number it was sent to. */
class WeddingLocator
{
    public function byWhatsAppPhoneNumberId(?string $phoneNumberId): ?Wedding
    {
        return $phoneNumberId ? $this->first(fn (OutreachConfig $c) => (string) $c->get('messaging.phone_number_id') === $phoneNumberId) : null;
    }

    public function byNumber(?string $digits): ?Wedding
    {
        if (! $digits) {
            return null;
        }

        return $this->first(fn (OutreachConfig $c) => in_array($digits, array_filter([
            Phone::normalize($c->get('branding.whatsapp_number'), $c->get('compliance.country_code', '255')),
            Phone::normalize($c->get('branding.caller_id'), $c->get('compliance.country_code', '255')),
        ]), true));
    }

    private function first(callable $match): ?Wedding
    {
        foreach (Wedding::whereNotNull('outreach_config')->cursor() as $wedding) {
            if ($match($wedding->outreach())) {
                return $wedding;
            }
        }

        return null;
    }
}
