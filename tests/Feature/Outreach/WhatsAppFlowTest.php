<?php

namespace Tests\Feature\Outreach;

use App\Models\FollowUp;
use App\Models\KnowledgeQuery;
use App\Models\OptOut;
use App\Outreach\ContactPolicy;
use App\Outreach\MessagingAgent;
use App\Outreach\OutreachDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\OutreachFixtures;
use Tests\TestCase;

class WhatsAppFlowTest extends TestCase
{
    use OutreachFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeLlm();
        $this->fakeWhatsApp();
    }

    public function test_pledged_guest_gets_swahili_intro_with_date_buttons(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding);

        app(MessagingAgent::class)->start($pledge, 'initial');

        $sent = $this->sentWhatsAppBodies()[0];
        $this->assertSame('255712345678', $sent['to']);
        $this->assertSame('list', $sent['interactive']['type'], 'Four date options are sent as a list.');
        $this->assertStringContainsString('Asante sana kwa ahadi yako ya TZS 500,000', $sent['interactive']['body']['text']);
        $this->assertSame(1, FollowUp::where('reason', 'no_reply')->where('status', 'pending')->count());
    }

    public function test_promise_by_the_20th_is_registered_thanked_and_silences_reminders_until_then(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding, 500000, 100000);
        app(MessagingAgent::class)->start($pledge, 'initial');

        $date = now('Africa/Dar_es_Salaam')->addDays(12)->toDateString();
        $this->llmReplies[] = json_encode([
            'reply_text' => 'Asante sana Baraka — nimeandika tarehe hiyo. Tunashukuru sana!',
            'intent' => 'promise_date', 'pledged_amount' => null, 'promised_date' => $date,
            'question' => null, 'fallback_used' => false, 'next_follow_up_at' => null,
        ]);

        $this->postWhatsApp('255712345678', ['type' => 'text', 'text' => ['body' => 'Nitatuma kufikia tarehe 20']], 'wamid.in1')->assertOk();

        $promise = $pledge->promises()->sole();
        $this->assertSame($date, $promise->promised_date->toDateString());
        $this->assertSame('open', $promise->status);

        $bodies = $this->sentWhatsAppBodies();
        $this->assertStringContainsString('Asante sana Baraka', end($bodies)['text']['body']);

        $this->assertSame(0, FollowUp::where('reason', 'no_reply')->where('status', 'pending')->count(), 'Reminder cancelled by the promise.');
        $this->assertTrue(FollowUp::where('reason', 'promise_due')->where('status', 'pending')->exists());
        $this->assertSame('promise_pending', (new ContactPolicy())->check($pledge->fresh(), 'whatsapp', 'reminder')['reason']);

        // The model saw the guest's words and was told to answer in Swahili.
        $this->assertStringContainsString('Always speak in Kiswahili', $this->llmCalls[0]['system']);
        $this->assertSame('user', $this->llmCalls[0]['messages'][0]['role']);
    }

    public function test_date_button_registers_promise_without_the_model(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding);
        app(MessagingAgent::class)->start($pledge, 'initial');

        $this->postWhatsApp('255712345678', ['type' => 'interactive', 'interactive' => ['list_reply' => ['id' => 'date:two_weeks', 'title' => 'Ndani ya wiki 2']]], 'wamid.b1')->assertOk();

        $this->assertSame(now('Africa/Dar_es_Salaam')->startOfDay()->addDays(14)->toDateString(), $pledge->promises()->sole()->promised_date->toDateString());
        $this->assertSame([], $this->llmCalls);
    }

    public function test_stop_opts_out_on_every_channel_forever(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding);
        app(MessagingAgent::class)->start($pledge, 'initial');

        $this->postWhatsApp('255712345678', ['type' => 'text', 'text' => ['body' => 'STOP']], 'wamid.stop')->assertOk();

        $this->assertTrue(OptOut::where('wedding_id', $wedding->id)->where('phone', '255712345678')->exists());
        $this->assertSame(0, FollowUp::where('pledge_id', $pledge->id)->where('status', 'pending')->count());
        $this->assertStringContainsString('hatutakutumia ujumbe tena', collect($this->sentWhatsAppBodies())->last()['text']['body']);

        $policy = new ContactPolicy();
        $this->assertSame('opted_out', $policy->check($pledge->fresh(), 'whatsapp', 'initial')['reason']);
        $this->assertSame('opted_out', $policy->check($pledge->fresh(), 'voice', 'initial')['reason']);

        // A scheduled campaign contact is skipped, and a later message gets no reply.
        $job = $wedding->outreachJobs()->create(['pledge_id' => $pledge->id, 'channel' => 'voice', 'scheduled_at' => now()]);
        app(OutreachDispatcher::class)->runJob($job->id);
        $this->assertSame('skipped', $job->fresh()->status);

        $count = count($this->sentWhatsAppBodies());
        $this->postWhatsApp('255712345678', ['type' => 'text', 'text' => ['body' => 'Habari?']], 'wamid.after')->assertOk();
        $this->assertCount($count, $this->sentWhatsAppBodies());
    }

    public function test_unknown_question_gets_graceful_fallback_and_is_logged(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding);
        app(MessagingAgent::class)->start($pledge, 'initial');

        $this->llmReplies[] = json_encode([
            'reply_text' => 'Swali zuri! Nitamwomba Mama Neema athibitishe na akurudie.',
            'intent' => 'question', 'pledged_amount' => null, 'promised_date' => null,
            'question' => 'Watoto wanaruhusiwa?', 'fallback_used' => true, 'next_follow_up_at' => null,
        ]);
        $this->postWhatsApp('255712345678', ['type' => 'text', 'text' => ['body' => 'Watoto wanaruhusiwa?']], 'wamid.q1')->assertOk();

        $query = KnowledgeQuery::sole();
        $this->assertTrue($query->fell_back);
        $this->assertSame('Watoto wanaruhusiwa?', $query->question);
        $this->assertTrue(FollowUp::where('reason', 'coordinator_question')->whereNull('channel')->exists());
    }

    public function test_duplicate_and_unsigned_webhooks_are_rejected_or_ignored(): void
    {
        $wedding = $this->makeWedding();
        $this->makePledge($wedding);
        $this->llmReplies[] = json_encode(['reply_text' => 'Karibu!', 'intent' => 'greeting']);

        $this->postWhatsApp('255712345678', ['type' => 'text', 'text' => ['body' => 'Habari']], 'wamid.dup')->assertOk();
        $this->postWhatsApp('255712345678', ['type' => 'text', 'text' => ['body' => 'Habari']], 'wamid.dup')->assertOk();
        $this->assertCount(1, $this->llmCalls, 'A redelivered message is processed once.');

        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'],
            json_encode(['entry' => [['changes' => [['value' => ['metadata' => ['phone_number_id' => 'PNID1'], 'messages' => [['from' => '255712345678', 'id' => 'x', 'type' => 'text', 'text' => ['body' => 'hi']]]]]]]]]))
            ->assertForbidden();
    }

    public function test_guest_with_no_pledge_can_pledge_over_whatsapp(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding, 0);
        $this->assertSame('none', $pledge->status);

        app(MessagingAgent::class)->start($pledge, 'initial');
        $this->assertSame('button', $this->sentWhatsAppBodies()[0]['interactive']['type']);

        $this->llmReplies[] = json_encode(['reply_text' => 'Asante sana kwa ahadi yako ya TZS 200,000!', 'intent' => 'pledge_amount', 'pledged_amount' => 200000]);
        $this->postWhatsApp('255712345678', ['type' => 'text', 'text' => ['body' => 'Naahidi laki mbili']], 'wamid.p1')->assertOk();

        $this->assertSame(200000.0, (float) $pledge->fresh()->amount);
        $this->assertSame('pledged', $pledge->fresh()->status);
    }

    public function test_switching_messaging_provider_is_config_only(): void
    {
        $wedding = $this->makeWedding(['messaging' => ['provider' => 'twilio_whatsapp']]);
        $pledge = $this->makePledge($wedding);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'])]);

        app(MessagingAgent::class)->start($pledge, 'initial');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.twilio.com') && $r['To'] === 'whatsapp:+255712345678' && str_contains($r['Body'], '1. Wiki hii'));
    }
}
