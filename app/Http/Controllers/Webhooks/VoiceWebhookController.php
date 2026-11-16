<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Outreach\Messaging\TwilioMessagingProvider;
use App\Outreach\VoiceAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Twilio voice callbacks for one call. Each URL carries the conversation id. */
class VoiceWebhookController extends Controller
{
    public function __construct(private readonly VoiceAgent $agent)
    {
    }

    public function answer(Request $request, Conversation $conversation): Response
    {
        $this->authorizeTwilio($request, $conversation);

        return $this->twiml($this->agent->answered($conversation, $request->post('AnsweredBy')));
    }

    public function turn(Request $request, Conversation $conversation): Response
    {
        $this->authorizeTwilio($request, $conversation);

        return $this->twiml($this->agent->turn($conversation, (string) $request->post('SpeechResult', '')));
    }

    public function status(Request $request, Conversation $conversation): Response
    {
        $this->authorizeTwilio($request, $conversation);
        $this->agent->status($conversation, (string) $request->post('CallStatus', ''));

        return response('', 204);
    }

    private function authorizeTwilio(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->channel === 'voice' && $request->post('CallSid') === $conversation->provider_ref, 404);
        abort_unless(TwilioMessagingProvider::verifySignature($request, $conversation->wedding->outreach()->secret('twilio_token')), 403);
    }

    private function twiml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }
}
