<?php

namespace App\Outreach\Messaging;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Meta WhatsApp Cloud API. */
class WhatsAppCloudProvider implements MessagingProvider
{
    private const GRAPH = 'https://graph.facebook.com/v21.0';

    public function __construct(private readonly string $token, private readonly string $phoneNumberId, private readonly bool $interactive = true)
    {
    }

    public function channel(): string { return 'whatsapp'; }

    public function supportsInteractive(): bool { return $this->interactive; }

    public function send(string $to, string $text, array $options = []): ?string
    {
        $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $to];

        if ($options && $this->interactive && count($options) <= 3) {
            $payload += ['type' => 'interactive', 'interactive' => [
                'type' => 'button',
                'body' => ['text' => $text],
                'action' => ['buttons' => array_map(fn ($o) => ['type' => 'reply', 'reply' => ['id' => $o['id'], 'title' => mb_substr($o['title'], 0, 20)]], $options)],
            ]];
        } elseif ($options && $this->interactive && count($options) <= 10) {
            $payload += ['type' => 'interactive', 'interactive' => [
                'type' => 'list',
                'body' => ['text' => $text],
                'action' => ['button' => 'Chagua', 'sections' => [['title' => 'Chaguo', 'rows' => array_map(fn ($o) => ['id' => $o['id'], 'title' => mb_substr($o['title'], 0, 24)], $options)]]],
            ]];
        } else {
            $payload += ['type' => 'text', 'text' => ['preview_url' => false, 'body' => NumberedOptions::append($text, $options)]];
        }

        $response = Http::withToken($this->token)->timeout(20)->post(self::GRAPH."/{$this->phoneNumberId}/messages", $payload);
        if ($response->failed()) {
            throw new MessagingException('WhatsApp Cloud error ('.$response->status().'): '.mb_substr($response->body(), 0, 500));
        }

        return $response->json('messages.0.id');
    }

    public static function verifySignature(Request $request, ?string $appSecret): bool
    {
        $header = (string) $request->header('X-Hub-Signature-256');
        if (! $appSecret || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $appSecret), $header);
    }

    /** @return InboundMessage[] */
    public static function parse(array $payload): array
    {
        $out = [];
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $recipient = $value['metadata']['phone_number_id'] ?? null;
                foreach ($value['messages'] ?? [] as $m) {
                    [$text, $optionId] = match ($m['type'] ?? '') {
                        'text' => [$m['text']['body'] ?? '', null],
                        'interactive' => isset($m['interactive']['button_reply'])
                            ? [$m['interactive']['button_reply']['title'] ?? '', $m['interactive']['button_reply']['id'] ?? null]
                            : [$m['interactive']['list_reply']['title'] ?? '', $m['interactive']['list_reply']['id'] ?? null],
                        'button' => [$m['button']['text'] ?? '', $m['button']['payload'] ?? null],
                        default => ['', null],
                    };
                    if (($m['from'] ?? null) && ($m['id'] ?? null) && ($text !== '' || $optionId)) {
                        $out[] = new InboundMessage(preg_replace('/\D/', '', $m['from']), $text, $optionId, $m['id'], 'whatsapp', $recipient);
                    }
                }
            }
        }

        return $out;
    }
}
