<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_is_public_and_linked_from_footer_and_signup(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Terms and Conditions')
            ->assertSee('Personal Data Protection Act')->assertSee('ACHA');

        $this->get('/')->assertOk()->assertSee(route('terms'));
        $this->get('/register')->assertOk()->assertSee(route('terms'));
    }

    public function test_contact_lines_hidden_until_configured(): void
    {
        config(['legal.email' => null]);
        $this->get('/terms')->assertDontSee('mailto:');

        config(['legal.email' => 'legal@example.test']);
        $this->get('/terms')->assertSee('mailto:legal@example.test', false);
    }
}
