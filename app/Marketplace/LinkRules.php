<?php

namespace App\Marketplace;

/** Only https links to known hosts are stored, so profiles cannot carry arbitrary redirects. */
final class LinkRules
{
    public static function hostAllowed(string $url, array $hosts): bool
    {
        if (! str_starts_with(strtolower($url), 'https://') || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> cleaned links; invalid ones produce validation errors upstream */
    public static function social(array $links, array &$errors): array
    {
        $clean = [];
        foreach (config('marketplace.social_hosts') as $platform => $hosts) {
            $url = trim((string) ($links[$platform] ?? ''));
            if ($url === '') {
                continue;
            }
            if (! self::hostAllowed($url, $hosts)) {
                $errors["social_links.$platform"] = 'Enter a full https:// '.ucfirst($platform).' link.';

                continue;
            }
            $clean[$platform] = $url;
        }

        return $clean;
    }
}
