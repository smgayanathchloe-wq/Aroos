<?php

namespace App\Outreach\Messaging;

final class InboundMessage
{
    public function __construct(
        public readonly string $from,          // digits-only
        public readonly string $text,
        public readonly ?string $optionId,     // id of the tapped button/list row, if any
        public readonly string $messageId,     // provider id, for idempotency
        public readonly string $channel,       // whatsapp | sms
        public readonly ?string $recipient,    // our number / phone_number_id, to find the wedding
    ) {
    }
}
