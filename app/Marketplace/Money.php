<?php

namespace App\Marketplace;

final class Money
{
    public static function format(float|string|null $amount, ?string $currency = null): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        return ($currency ?: config('marketplace.currency', 'TZS')).' '.number_format((float) $amount);
    }
}
