<?php

namespace Tests\Feature\Outreach;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OutreachFixtures;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use OutreachFixtures, RefreshDatabase;

    public function test_pages_render_for_the_owner(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding);
        $conversation = $pledge->conversations()->create(['wedding_id' => $wedding->id, 'channel' => 'whatsapp', 'status' => 'open', 'extracted' => ['intent' => 'promise_date']]);
        $conversation->addTurn('assistant', 'Habari Baraka');

        foreach (['outreach.index', 'outreach.settings', 'outreach.conversations', 'outreach.followups'] as $route) {
            $this->actingAs($wedding->user)->get(route($route, $wedding))->assertOk();
        }
        $this->actingAs($wedding->user)->get(route('outreach.conversation', [$wedding, $conversation]))->assertOk()->assertSee('promise_date');
    }

    public function test_other_users_cannot_see_a_weddings_outreach(): void
    {
        $wedding = $this->makeWedding();
        $stranger = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => bcrypt('p')]);

        $this->actingAs($stranger)->get(route('outreach.index', $wedding))->assertForbidden();
    }

    public function test_invalid_config_is_rejected_and_secrets_are_never_echoed(): void
    {
        $wedding = $this->makeWedding();

        $this->actingAs($wedding->user)->put(route('outreach.settings.update', $wedding), ['config' => json_encode(['ai' => ['provider' => 'nope']])])
            ->assertSessionHasErrors();

        $this->actingAs($wedding->user)->put(route('outreach.secrets.update', $wedding), ['openai_api_key' => 'sk-super-secret'])->assertRedirect();
        $this->assertSame('sk-super-secret', $wedding->fresh()->outreach_secrets['openai_api_key']);
        $this->actingAs($wedding->user)->get(route('outreach.settings', $wedding))->assertDontSee('sk-super-secret');
    }

    public function test_saving_settings_keeps_wedding_facts_live(): void
    {
        $wedding = $this->makeWedding();
        $edited = $wedding->outreach()->data;
        $edited['branding']['assistant_name'] = 'Neema';

        $this->actingAs($wedding->user)->put(route('outreach.settings.update', $wedding), ['config' => json_encode($edited)])->assertSessionHasNoErrors();
        $wedding->refresh();
        $this->assertArrayNotHasKey('couple_names', $wedding->outreach_config['wedding'] ?? []);

        $wedding->update(['partner_b' => 'Hamisi']);
        $this->assertSame('Asha & Hamisi', $wedding->fresh()->outreach()->get('wedding.couple_names'));
        $this->assertSame('Neema', $wedding->fresh()->outreach()->get('branding.assistant_name'));
    }
}
