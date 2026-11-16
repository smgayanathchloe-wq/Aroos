<?php

namespace Tests\Feature\Outreach;

use App\Models\Conversation;
use App\Models\FollowUp;
use App\Outreach\VoiceAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\OutreachFixtures;
use Tests\TestCase;

class VoiceFlowTest extends TestCase
{
    use OutreachFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeLlm();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'CA123'])]);
    }

    private function twilio(string $uri, array $params)
    {
        $url = url($uri);
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }

        return $this->post($uri, $params, ['X-Twilio-Signature' => base64_encode(hash_hmac('sha1', $data, 'tw-token', true))]);
    }

    public function test_full_call_in_swahili_registers_a_promise(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding, 500000, 200000);

        $conversation = app(VoiceAgent::class)->start($pledge, 'initial');
        $this->assertSame('dialing', $conversation->status);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'Calls.json') && $r['To'] === '+255712345678' && $r['MachineDetection'] === 'Enable');

        $answer = $this->twilio("/webhooks/voice/{$conversation->id}/answer", ['CallSid' => 'CA123', 'AnsweredBy' => 'human'])->assertOk();
        $this->assertStringContainsString('language="sw-TZ"', $answer->getContent());
        $this->assertStringContainsString('Habari, mimi ni Amani', $answer->getContent());

        $this->llmReplies[] = json_encode(['say' => 'Asante Baraka. Una ahadi ya TZS 500,000, tumepokea TZS 200,000. Unatarajia kukamilisha lini?', 'end_call' => false]);
        $turn = $this->twilio("/webhooks/voice/{$conversation->id}/turn", ['CallSid' => 'CA123', 'SpeechResult' => 'Ndiyo, ni mimi'])->assertOk();
        $this->assertStringContainsString('<Gather input="speech" language="sw-TZ"', $turn->getContent());

        $date = now('Africa/Dar_es_Salaam')->addDays(10)->toDateString();
        $this->llmReplies[] = json_encode(['say' => 'Asante sana. Hongera tena kutoka kwetu sote. Kwaheri.', 'end_call' => true]);
        $this->llmReplies[] = json_encode([
            'outcome' => 'promise', 'promised_date' => $date, 'promised_amount' => 300000, 'confidence' => 'high',
            'guest_language' => 'sw', 'questions_asked' => [], 'unanswered_questions' => [], 'fallback_used' => false,
            'summary' => 'Guest will pay the remaining 300,000 in ten days.', 'callback_time' => null,
        ]);
        $end = $this->twilio("/webhooks/voice/{$conversation->id}/turn", ['CallSid' => 'CA123', 'SpeechResult' => 'Nitamaliza wiki ijayo'])->assertOk();
        $this->assertStringContainsString('<Hangup/>', $end->getContent());

        $conversation->refresh();
        $this->assertSame('done', $conversation->status);
        $this->assertSame('promise', $conversation->outcome);
        $this->assertSame($date, $conversation->extracted['promised_date']);
        $this->assertSame($date, $pledge->promises()->sole()->promised_date->toDateString());
        $this->assertSame('Guest will pay the remaining 300,000 in ten days.', $pledge->fresh()->last_ai_summary);

        // A late "completed" status callback must not register twice.
        $this->twilio("/webhooks/voice/{$conversation->id}/status", ['CallSid' => 'CA123', 'CallStatus' => 'completed'])->assertNoContent();
        $this->assertSame(1, $pledge->promises()->count());
    }

    public function test_no_answer_schedules_one_retry_next_day(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding);
        $conversation = app(VoiceAgent::class)->start($pledge, 'initial');

        $this->twilio("/webhooks/voice/{$conversation->id}/status", ['CallSid' => 'CA123', 'CallStatus' => 'no-answer'])->assertNoContent();

        $this->assertSame('no_answer', $conversation->fresh()->status);
        $retry = FollowUp::where('reason', 'no_answer_retry')->sole();
        $this->assertSame('voice', $retry->channel);
        $this->assertTrue($retry->due_at->isAfter(now()));
    }

    public function test_voice_webhooks_require_a_valid_twilio_signature(): void
    {
        $wedding = $this->makeWedding();
        $conversation = app(VoiceAgent::class)->start($this->makePledge($wedding), 'initial');

        $this->post("/webhooks/voice/{$conversation->id}/answer", ['CallSid' => 'CA123'], ['X-Twilio-Signature' => 'forged'])->assertForbidden();
        $this->assertSame('dialing', Conversation::find($conversation->id)->status);
    }
}
