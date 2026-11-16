<?php

namespace App\Outreach\Messaging;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Twilio Messages API for WhatsApp ("whatsapp:+255...") or SMS. Free-form
 * interactive buttons need pre-approved Content templates on Twilio, so quick
 * replies are sent as numbered text.
 */
class TwilioMessagingProvider implements MessagingProvider
{
    public function __construct(
        private readonly string $sid,
        private readonly string $token,
        private readonly string $from,
        private readonly string $channel = 'whatsapp',
    ) {
    }

    public function channel(): string { return $this->channel; }

    public function supportsInteractive(): bool { return false; }

    public function send(string $to, string $text, array $options = []): ?string
    {
        $prefix = $this->channel === 'whatsapp' ? 'whatsapp:' : '';
        $response = Http::asForm()->withBasicAuth($this->sid, $this->token)->timeout(20)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                'From' => $prefix.'+'.preg_replace('/\D/', '', $this->from),
                'To' => $prefix.'+'.$to,
                'Body' => NumberedOptions::append($text, $options),
            ]);
        if ($response->failed()) {
            throw new MessagingException('Twilio error ('.$response->status().'): '.mb_substr($response->body(), 0, 500));
        }

        return $response->json('sid');
    }

    /** X-Twilio-Signature: base64(HMAC-SHA1(url + sorted POST key/values, auth token)). */
    public static function verifySignature(Request $request, ?string $authToken): bool
    {
        $signature = (string) $request->header('X-Twilio-Signature');
        if (! $authToken || $signature === '') {
            return false;
        }
        $params = $request->post();
        ksort($params);
        $data = $request->fullUrl();
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }

        return hash_equals(base64_encode(hash_hmac('sha1', $data, $authToken, true)), $signature);
    }

    public static function parse(Request $request): ?InboundMessage
    {
        $from = (string) $request->post('From', '');
        $sid = (string) $request->post('MessageSid', '');
        if ($from === '' || $sid === '') {
            return null;
        }

        return new InboundMessage(
            preg_replace('/\D/', '', $from),
            (string) $request->post('Body', ''),
            $request->post('ButtonPayload'),
            $sid,
            str_starts_with($from, 'whatsapp:') ? 'whatsapp' : 'sms',
            preg_replace('/\D/', '', (string) $request->post('To', '')),
        );
    }
}
