<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Pledge;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PledgeBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeWedding(User $user, string $slug): Wedding
    {
        $wedding = Wedding::create([
            'user_id' => $user->id,
            'partner_a' => 'Asha',
            'partner_b' => 'Juma',
            'slug' => $slug,
            'currency' => 'TZS',
        ]);

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

        return $wedding;
    }

    public function test_it_deletes_all_pledges_and_contributions(): void
    {
        $user = User::create(['name' => 'O', 'email' => 'o1@example.com', 'password' => bcrypt('p')]);
        $wedding = $this->makeWedding($user, 'w-1');

        $this->actingAs($user)
            ->delete(route('pledges.destroyAll', $wedding), ['confirmation' => 'DELETE', 'scope' => 'all'])
            ->assertRedirect();

        $this->assertSame(0, Pledge::count());
        $this->assertSame(0, Contribution::count());
    }

    public function test_it_can_delete_contributions_only(): void
    {
        $user = User::create(['name' => 'O', 'email' => 'o2@example.com', 'password' => bcrypt('p')]);
        $wedding = $this->makeWedding($user, 'w-2');

        $this->actingAs($user)
            ->delete(route('pledges.destroyAll', $wedding), ['confirmation' => 'DELETE', 'scope' => 'contributions'])
            ->assertRedirect();

        $this->assertSame(1, Pledge::count(), 'Pledges must survive a contributions-only clear.');
        $this->assertSame(0, Contribution::count());

        // The pledge was 'partial'; with its payments gone it must not keep
        // claiming money was received.
        $this->assertSame('pledged', Pledge::first()->status);
    }

    public function test_it_refuses_without_the_confirmation_phrase(): void
    {
        $user = User::create(['name' => 'O', 'email' => 'o3@example.com', 'password' => bcrypt('p')]);
        $wedding = $this->makeWedding($user, 'w-3');

        $this->actingAs($user)
            ->delete(route('pledges.destroyAll', $wedding), ['confirmation' => 'yes', 'scope' => 'all'])
            ->assertSessionHasErrors('confirmation');

        $this->assertSame(1, Pledge::count(), 'Nothing may be deleted without confirmation.');
        $this->assertSame(1, Contribution::count());
    }

    public function test_it_never_touches_another_wedding(): void
    {
        $user = User::create(['name' => 'O', 'email' => 'o4@example.com', 'password' => bcrypt('p')]);
        $target = $this->makeWedding($user, 'w-target');
        $other = $this->makeWedding($user, 'w-other');

        $this->actingAs($user)
            ->delete(route('pledges.destroyAll', $target), ['confirmation' => 'DELETE', 'scope' => 'all']);

        $this->assertSame(0, Pledge::where('wedding_id', $target->id)->count());
        $this->assertSame(1, Pledge::where('wedding_id', $other->id)->count(), 'Other weddings must be untouched.');
        $this->assertSame(1, Contribution::where('wedding_id', $other->id)->count());
    }

    public function test_guests_cannot_bulk_delete(): void
    {
        $user = User::create(['name' => 'O', 'email' => 'o5@example.com', 'password' => bcrypt('p')]);
        $wedding = $this->makeWedding($user, 'w-5');

        $this->delete(route('pledges.destroyAll', $wedding), ['confirmation' => 'DELETE', 'scope' => 'all'])
            ->assertRedirect();

        $this->assertSame(1, Pledge::count(), 'Unauthenticated requests must not delete anything.');
    }
}
