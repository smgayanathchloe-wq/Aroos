<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\HandleInboundMessage;
use App\Models\WebhookReceipt;
use App\Outreach\Messaging\TwilioMessagingProvider;
use App\Outreach\Messaging\WhatsAppCloudProvider;
use App\Outreach\WeddingLocator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function __construct(private readonly WeddingLocator $locator)
    {
    }

    /** Meta subscription handshake. */
    public function verify(Request $request): Response
    {
        $token = config('outreach.whatsapp_verify_token');
        abort_unless($token && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'));
    }

    /** WhatsApp Cloud API inbound messages. */
    public function cloud(Request $request): Response
    {
        $messages = WhatsAppCloudProvider::parse($request->json()->all());
        if (! $messages) {
            // Delivery/read statuses: still require a valid signature from some tenant? Nothing to do.
            return response('', 200);
        }

        $wedding = $this->locator->byWhatsAppPhoneNumberId($messages[0]->recipient);
        abort_unless($wedding, 404);
        abort_unless(WhatsAppCloudProvider::verifySignature($request, $wedding->outreach()->secret('whatsapp_app_secret')), 403);

        foreach ($messages as $message) {
            if (WebhookReceipt::firstTime('whatsapp_cloud', $message->messageId)) {
                HandleInboundMessage::dispatch($wedding->id, $message);
            }
        }

        return response('', 200);
    }

    /** Twilio inbound WhatsApp or SMS. */
    public function twilio(Request $request): Response
    {
        $message = TwilioMessagingProvider::parse($request);
        abort_unless($message, 400);

        $wedding = $this->locator->byNumber($message->recipient);
        abort_unless($wedding, 404);
        abort_unless(TwilioMessagingProvider::verifySignature($request, $wedding->outreach()->secret('twilio_token')), 403);

        if (WebhookReceipt::firstTime('twilio_message', $message->messageId)) {
            HandleInboundMessage::dispatch($wedding->id, $message);
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml']);
    }
}
