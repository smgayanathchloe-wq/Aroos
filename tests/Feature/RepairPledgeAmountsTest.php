<?php

namespace Tests\Feature;

use App\Models\Pledge;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The repair command is the one piece of this work that writes to the live
 * ledger and destroys data (it clears notes), so its guards are tested
 * directly rather than trusted.
 */
class RepairPledgeAmountsTest extends TestCase
{
    use RefreshDatabase;

    private function makeWedding(string $email, string $slug): Wedding
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        return Wedding::create([
            'user_id' => $user->id,
            'partner_a' => 'Asha',
            'partner_b' => 'Juma',
            'slug' => $slug,
            'currency' => 'TZS',
        ]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $wedding = $this->makeWedding('d1@example.com', 'd-1');
        $wedding->pledges()->create([
            'pledger_name' => 'Asha',
            'amount' => 0,
            'notes' => '1500000',
            'status' => 'pledged',
        ]);

        $this->artisan('pledges:repair-amounts')
            ->expectsOutputToContain('would be repaired')
            ->assertSuccessful();

        $pledge = Pledge::first();
        $this->assertEquals(0, (float) $pledge->amount, 'A dry run must not write.');
        $this->assertSame('1500000', $pledge->notes);
    }

    public function test_commit_moves_the_figure_into_amount(): void
    {
        $wedding = $this->makeWedding('d2@example.com', 'd-2');
        $wedding->pledges()->create([
            'pledger_name' => 'Asha',
            'amount' => 0,
            'notes' => '1,500,000',
            'status' => 'pledged',
        ]);

        $this->artisan('pledges:repair-amounts --commit')->assertSuccessful();

        $pledge = Pledge::first();
        $this->assertEquals(1500000, (float) $pledge->amount);
        $this->assertNull($pledge->notes);
    }

    public function test_it_recomputes_status_after_repair(): void
    {
        $wedding = $this->makeWedding('d3@example.com', 'd-3');
        $pledge = $wedding->pledges()->create([
            'pledger_name' => 'Adv. Jasper',
            'amount' => 0,
            'notes' => '2500000',
            'status' => 'fulfilled', // wrong: set when amount was 0
        ]);
        $wedding->contributions()->create([
            'pledge_id' => $pledge->id,
            'contributor_name' => 'Adv. Jasper',
            'amount' => 1000000,
            'method' => 'other',
        ]);

        $this->artisan('pledges:repair-amounts --commit')->assertSuccessful();

        $pledge->refresh();
        $this->assertEquals(2500000, (float) $pledge->amount);
        $this->assertSame('partial', $pledge->status, 'Still owing 1.5m, so not fulfilled.');
    }

    public function test_it_leaves_genuine_text_notes_alone(): void
    {
        $wedding = $this->makeWedding('d4@example.com', 'd-4');
        $wedding->pledges()->create([
            'pledger_name' => 'Asha',
            'amount' => 0,
            'notes' => 'Will confirm the amount next week',
            'status' => 'pledged',
        ]);

        $this->artisan('pledges:repair-amounts --commit')->assertSuccessful();

        $pledge = Pledge::first();
        $this->assertEquals(0, (float) $pledge->amount);
        $this->assertSame('Will confirm the amount next week', $pledge->notes, 'Prose must never be eaten.');
    }

    public function test_it_leaves_pledges_that_already_have_an_amount(): void
    {
        $wedding = $this->makeWedding('d5@example.com', 'd-5');
        $wedding->pledges()->create([
            'pledger_name' => 'Asha',
            'amount' => 500000,
            'notes' => '999999',
            'status' => 'pledged',
        ]);

        $this->artisan('pledges:repair-amounts --commit')->assertSuccessful();

        $pledge = Pledge::first();
        $this->assertEquals(500000, (float) $pledge->amount, 'A good amount must not be overwritten.');
        $this->assertSame('999999', $pledge->notes);
    }

    public function test_the_wedding_option_scopes_the_repair(): void
    {
        $target = $this->makeWedding('d6@example.com', 'd-6');
        $other = $this->makeWedding('d7@example.com', 'd-7');

        foreach ([$target, $other] as $w) {
            $w->pledges()->create([
                'pledger_name' => 'Asha',
                'amount' => 0,
                'notes' => '300000',
                'status' => 'pledged',
            ]);
        }

        $this->artisan('pledges:repair-amounts --commit --wedding='.$target->id)->assertSuccessful();

        $this->assertEquals(300000, (float) Pledge::where('wedding_id', $target->id)->first()->amount);
        $this->assertEquals(0, (float) Pledge::where('wedding_id', $other->id)->first()->amount, 'Other weddings must be untouched.');
    }

    public function test_it_reports_when_there_is_nothing_to_do(): void
    {
        $this->makeWedding('d8@example.com', 'd-8');

        $this->artisan('pledges:repair-amounts')
            ->expectsOutputToContain('Nothing to repair')
            ->assertSuccessful();
    }
}
