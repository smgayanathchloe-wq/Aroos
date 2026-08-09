<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders the pledges index itself. The behavioural tests hit the endpoints
 * directly, so a Blade error in the page would otherwise go unnoticed.
 */
class PledgesPageRenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeWedding(User $user, string $slug): Wedding
    {
        return Wedding::create([
            'user_id' => $user->id,
            'partner_a' => 'Asha',
            'partner_b' => 'Juma',
            'slug' => $slug,
            'currency' => 'TZS',
        ]);
    }

    public function test_page_renders_with_danger_zone_when_records_exist(): void
    {
        $user = User::create(['name' => 'O', 'email' => 'r1@example.com', 'password' => bcrypt('p')]);
        $wedding = $this->makeWedding($user, 'r-1');

        $pledge = $wedding->pledges()->create([
            'pledger_name' => 'Asha Mwinyi',
            'amount' => 500000,
            'status' => 'partial',
        ]);

        $wedding->contributions()->create([
            'pledge_id' => $pledge->id,
            'contributor_name' => 'Asha Mwinyi',
            'amount' => 200000,
            'method' => 'cash',
        ]);

        $response = $this->actingAs($user)->get(route('pledges.index', $wedding));

        $response->assertOk();
        $response->assertSee('Danger zone');
        $response->assertSee('Type DELETE to confirm', false);
        // The import entry point lives on this page too.
        $response->assertSee('Import from spreadsheet');
    }

    public function test_danger_zone_is_hidden_when_there_is_nothing_to_delete(): void
    {
        $user = User::create(['name' => 'O', 'email' => 'r2@example.com', 'password' => bcrypt('p')]);
        $wedding = $this->makeWedding($user, 'r-2');

        $response = $this->actingAs($user)->get(route('pledges.index', $wedding));

        $response->assertOk();
        $response->assertDontSee('Danger zone');
    }

    public function test_import_page_renders(): void
    {
        $user = User::create(['name' => 'O', 'email' => 'r3@example.com', 'password' => bcrypt('p')]);
        $wedding = $this->makeWedding($user, 'r-3');

        $this->actingAs($user)
            ->get(route('pledges.import.index', $wedding))
            ->assertOk()
            ->assertSee('Import pledges');
    }
}
