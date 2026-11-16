<?php

namespace App\Outreach\Messaging;

interface MessagingProvider
{
    public function channel(): string; // whatsapp | sms

    public function supportsInteractive(): bool;

    /**
     * @param  string  $to  digits-only international number
     * @param  array<int, array{id: string, title: string}>  $options  quick replies; rendered as
     *         buttons (<=3), a list (>3), or numbered text when interactive is unsupported
     * @return string|null provider message id
     */
    public function send(string $to, string $text, array $options = []): ?string;
}
