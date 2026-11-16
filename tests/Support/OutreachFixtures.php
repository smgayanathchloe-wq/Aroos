<?php

namespace Tests\Support;

use App\Models\Pledge;
use App\Models\User;
use App\Models\Wedding;
use App\Outreach\Llm\LlmFactory;
use App\Outreach\Llm\LlmProvider;
use App\Outreach\OutreachConfig;
use Illuminate\Support\Facades\Http;

trait OutreachFixtures
{
    /** Replies the fake model will give, in order. */
    protected array $llmReplies = [];

    /** System prompts / message lists the fake model received. */
    protected array $llmCalls = [];

    protected function makeWedding(array $config = [], array $secrets = []): Wedding
    {
        $user = User::create(['name' => 'Owner', 'email' => uniqid().'@example.com', 'password' => bcrypt('p')]);

        return Wedding::create([
            'user_id' => $user->id,
            'partner_a' => 'Asha',
            'partner_b' => 'Juma',
            'currency' => 'TZS',
            'wedding_date' => now()->addDays(60)->toDateString(),
            'outreach_config' => OutreachConfig::merge([
                'enabled' => true,
                'branding' => ['org_name' => 'Kamati ya Harusi ya Asha na Juma', 'whatsapp_number' => '+255700000001', 'caller_id' => '+255700000001'],
                'wedding' => ['venue_name' => 'Ukumbi wa Mlimani', 'contact_person_name' => 'Mama Neema', 'dress_code' => 'Vazi rasmi'],
                'messaging' => ['provider' => 'whatsapp_cloud', 'phone_number_id' => 'PNID1'],
                'compliance' => ['calling_hours_start' => '00:00', 'calling_hours_end' => '23:59'],
            ], $config),
            'outreach_secrets' => $secrets + [
                'whatsapp_token' => 'wa-token', 'whatsapp_app_secret' => 'wa-secret',
                'twilio_sid' => 'AC123', 'twilio_token' => 'tw-token', 'anthropic_api_key' => 'sk-test',
            ],
        ]);
    }

    protected function makePledge(Wedding $wedding, float $amount = 500000, float $paid = 0, array $attrs = []): Pledge
    {
        $pledge = $wedding->pledges()->create($attrs + [
            'pledger_name' => 'Baraka Mushi',
            'pledger_phone' => '0712 345 678',
            'amount' => $amount,
            'outreach_consent' => true,
            'status' => Pledge::statusFor($amount, 0),
        ]);
        if ($paid > 0) {
            $wedding->contributions()->create(['pledge_id' => $pledge->id, 'contributor_name' => 'Baraka Mushi', 'amount' => $paid, 'method' => 'cash']);
            $pledge->refreshStatus();
        }

        return $pledge->fresh();
    }

    protected function fakeLlm(): void
    {
        $test = $this;
        $this->app->instance(LlmFactory::class, new class($test) extends LlmFactory
        {
            public function __construct(private $test)
            {
            }

            public function make(OutreachConfig $config): LlmProvider
            {
                $test = $this->test;

                return new class($test) implements LlmProvider
                {
                    public function __construct(private $test)
                    {
                    }

                    public function complete(string $system, array $messages, string $effort = 'low'): string
                    {
                        return $this->test->nextLlmReply($system, $messages);
                    }
                };
            }
        });
    }

    public function nextLlmReply(string $system, array $messages): string
    {
        $this->llmCalls[] = ['system' => $system, 'messages' => $messages];
        if (! $this->llmReplies) {
            throw new \RuntimeException('Unexpected LLM call');
        }

        return array_shift($this->llmReplies);
    }

    protected function fakeWhatsApp(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]])]);
    }

    /** Signed Meta webhook POST carrying one inbound message. */
    protected function postWhatsApp(string $from, array $message, string $id)
    {
        $body = json_encode(['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => 'PNID1'],
            'messages' => [$message + ['from' => $from, 'id' => $id]],
        ]]]]]]);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'wa-secret'),
        ], $body);
    }

    protected function sentWhatsAppBodies(): array
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn ($r) => str_contains($r->url(), 'graph.facebook.com'))
            ->map(fn ($r) => $r->data())->values()->all();
    }
}
