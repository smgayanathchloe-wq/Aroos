<?php

namespace App\Outreach;

final class Phone
{
    /** Digits-only international form, e.g. "0712 345 678" -> "255712345678". */
    public static function normalize(?string $phone, string $countryCode = '255'): ?string
    {
        if ($phone === null) {
            return null;
        }
        $phone = preg_replace('/^whatsapp:/i', '', trim($phone));
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($phone, '+') || str_starts_with($digits, $countryCode)) {
            return $digits;
        }
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        return $countryCode.ltrim($digits, '0');
    }

    public static function e164(string $digits): string
    {
        return '+'.$digits;
    }
}
