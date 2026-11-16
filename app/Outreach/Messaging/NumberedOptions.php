<?php

namespace App\Outreach\Messaging;

/** Plain-text fallback for quick replies: "1. Wiki hii" and so on. */
final class NumberedOptions
{
    public static function append(string $text, array $options): string
    {
        if (! $options) {
            return $text;
        }
        $lines = array_map(fn ($o, $i) => ($i + 1).'. '.$o['title'], $options, array_keys($options));

        return $text."\n\n".implode("\n", $lines);
    }

    /** Map a reply like "2" back to the option offered at that position. */
    public static function match(string $reply, array $options): ?string
    {
        $reply = trim($reply);
        if (ctype_digit($reply) && isset($options[(int) $reply - 1])) {
            return $options[(int) $reply - 1]['id'];
        }
        foreach ($options as $o) {
            if (mb_strtolower($reply) === mb_strtolower($o['title'])) {
                return $o['id'];
            }
        }

        return null;
    }
}
