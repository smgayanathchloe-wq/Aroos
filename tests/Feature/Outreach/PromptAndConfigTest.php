<?php

namespace Tests\Feature\Outreach;

use App\Outreach\AgentJson;
use App\Outreach\OutreachConfig;
use App\Outreach\PromptRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OutreachFixtures;
use Tests\TestCase;

class PromptAndConfigTest extends TestCase
{
    use OutreachFixtures, RefreshDatabase;

    public function test_templates_resolve_placeholders_from_config_and_pledge_in_swahili(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding, 500000, 200000);
        $renderer = new PromptRenderer($wedding->outreach());

        $text = $renderer->message('intro_partial', $pledge, 'sw');

        $this->assertStringContainsString('Habari Baraka', $text);
        $this->assertStringContainsString('TZS 200,000', $text);
        $this->assertStringContainsString('TZS 300,000', $text);
        $this->assertStringContainsString('Asha & Juma', $text);
        $this->assertStringNotContainsString('{{', $text);
    }

    public function test_missing_placeholders_fall_back_to_neutral_swahili_wording(): void
    {
        $wedding = $this->makeWedding(['branding' => ['org_name' => null], 'wedding' => ['contact_person_name' => null]]);
        $pledge = $this->makePledge($wedding);
        $renderer = new PromptRenderer($wedding->outreach());

        $this->assertStringContainsString('kamati ya harusi', $renderer->message('intro_pledged', $pledge, 'sw'));
        $this->assertStringContainsString('mratibu wa harusi', $renderer->message('fallback', $pledge, 'sw'));
    }

    public function test_agent_prompts_are_fully_rendered_and_demand_swahili(): void
    {
        $wedding = $this->makeWedding(['wedding' => ['extra_faqs' => [['q' => 'Kuna maegesho?', 'a' => 'Ndiyo, bure.']]]]);
        $pledge = $this->makePledge($wedding);
        $renderer = new PromptRenderer($wedding->outreach());

        foreach ([$renderer->voiceSystemPrompt($pledge, 'sw'), $renderer->messagingSystemPrompt($pledge, 'sw')] as $prompt) {
            $this->assertStringNotContainsString('{{', $prompt);
            $this->assertStringNotContainsString('LANGUAGE_RULE', $prompt);
            $this->assertStringContainsString('Always speak in Kiswahili', $prompt);
            $this->assertStringContainsString('Kuna maegesho?', $prompt);
            $this->assertStringContainsString('TZS 500,000', $prompt);
        }
    }

    public function test_default_language_is_swahili(): void
    {
        $this->assertSame('sw', OutreachConfig::defaults()['branding']['language']);
        $this->assertSame('sw-TZ', OutreachConfig::defaults()['voice']['stt_language']);
        $this->assertSame('sw', $this->makeWedding()->outreach()->language());
    }

    public function test_validator_rejects_bad_config_and_accepts_defaults(): void
    {
        $this->assertSame([], OutreachConfig::validate(OutreachConfig::defaults()));

        $errors = OutreachConfig::validate([
            'ai' => ['provider' => 'mystery'],
            'compliance' => ['calling_hours_start' => '20:00', 'calling_hours_end' => '09:00'],
            'wedding' => ['wedding_date' => '12/09/2026'],
        ]);

        $this->assertArrayHasKey('ai.provider', $errors);
        $this->assertArrayHasKey('compliance.calling_hours_end', $errors);
        $this->assertArrayHasKey('wedding.wedding_date', $errors);
    }

    public function test_extraction_parsing_tolerates_prose_and_rejects_bad_values(): void
    {
        $raw = "Here you go:\n```json\n{\"outcome\":\"promise\",\"promised_date\":\"2026-02-30\",\"promised_amount\":\"300,000\",\"confidence\":\"sure\",\"questions_asked\":[\"Ukumbi uko wapi?\", 5],\"summary\":\"Ok\"}\n```";
        $parsed = AgentJson::callOutcome(AgentJson::decode($raw));

        $this->assertSame('promise', $parsed['outcome']);
        $this->assertNull($parsed['promised_date'], 'An impossible date must not be stored.');
        $this->assertSame(300000.0, $parsed['promised_amount']);
        $this->assertSame('low', $parsed['confidence']);
        $this->assertSame(['Ukumbi uko wapi?'], $parsed['questions_asked']);

        $this->assertNull(AgentJson::callOutcome(['outcome' => 'teleported'])['outcome']);
        $this->expectException(\InvalidArgumentException::class);
        AgentJson::decode('no json here');
    }

    public function test_config_diff_stores_only_overrides(): void
    {
        $base = OutreachConfig::defaults();
        $edited = $base;
        $edited['branding']['assistant_name'] = 'Neema';

        $this->assertSame(['branding' => ['assistant_name' => 'Neema']], OutreachConfig::diff($base, $edited));
    }
}
