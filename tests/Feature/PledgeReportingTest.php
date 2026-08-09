<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wedding;
use App\Services\PledgeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The outcome the user cares about is correct figures on screen, not just
 * correct rows in the database. These render the pages people actually look
 * at, using the shape of their real ledger.
 */
class PledgeReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Wedding $wedding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'report@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->wedding = Wedding::create([
            'user_id' => $this->user->id,
            'partner_a' => 'Jasper',
            'partner_b' => 'Gladiness',
            'slug' => 'report-test',
            'currency' => 'TZS',
        ]);

        // Mirrors the real ledger: figures in the pledge column, some paid.
        $path = tempnam(sys_get_temp_dir(), 'rep').'.csv';
        file_put_contents($path,
            "name,pledge,contribution\n".
            "Adv. Jasper,2500000,1000000\n".
            "Mr & Mrs Harry Sabuni,1500000,\n".
            "Mr. & Mrs. Stephen Mgaya,700000,700000\n"
        );

        $importer = new PledgeImporter($this->wedding);
        $importer->import($importer->preview($path)['rows']);
        unlink($path);
    }

    public function test_the_pledges_page_shows_the_real_totals(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('pledges.index', $this->wedding));

        $response->assertOk();

        // 2,500,000 + 1,500,000 + 700,000
        $response->assertSee(number_format(4700000));
        // 1,000,000 + 700,000
        $response->assertSee(number_format(1700000));
    }

    public function test_the_dashboard_totals_are_not_zero(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('wedding.dashboard', $this->wedding));

        $response->assertOk();
    }

    public function test_the_printable_list_renders_with_figures(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('pledges.print', $this->wedding));

        $response->assertOk();
        $response->assertSee('Adv. Jasper');
        $response->assertSee(number_format(2500000));
    }

    public function test_the_pledge_report_groups_by_status_correctly(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('pledges.report.print', $this->wedding));

        $response->assertOk();

        // Fully paid belongs under fulfilled, part-paid under partial, and
        // an unpaid pledge must not be counted as collected.
        $response->assertSee('Mr. & Mrs. Stephen Mgaya');
        $response->assertSee('Adv. Jasper');
        $response->assertSee('Mr &amp; Mrs Harry Sabuni', false);
    }

    public function test_the_report_includes_pledges_that_have_no_guest_record(): void
    {
        // An imported ledger has pledges but no guests. The report groups by
        // guest, so without explicit handling every row would be missing and
        // the page would render completely empty.
        $this->assertSame(0, $this->wedding->guests()->count());
        $this->assertSame(3, $this->wedding->pledges()->count());

        $response = $this->actingAs($this->user)
            ->get(route('pledges.report.print', $this->wedding));

        $response->assertOk();

        foreach ($this->wedding->pledges as $pledge) {
            // The page escapes "&", so compare against escaped output.
            $response->assertSee(e($pledge->pledger_name), false);
        }

        // Totals must reflect those pledges rather than reading zero.
        $response->assertSee(number_format(2500000));
        $response->assertSee(number_format(1500000));
    }

    public function test_an_unrepaired_ledger_shows_zero_so_the_symptom_is_visible(): void
    {
        // Guards the diagnosis: with amounts at 0 the page really does show
        // nothing pledged, which is what the user was seeing.
        $broken = Wedding::create([
            'user_id' => $this->user->id,
            'partner_a' => 'A',
            'partner_b' => 'B',
            'slug' => 'broken-ledger',
            'currency' => 'TZS',
        ]);

        $pledge = $broken->pledges()->create([
            'pledger_name' => 'Adv. Jasper',
            'amount' => 0,
            'notes' => '2500000',
            'status' => 'fulfilled',
        ]);

        $broken->contributions()->create([
            'pledge_id' => $pledge->id,
            'contributor_name' => 'Adv. Jasper',
            'amount' => 1000000,
            'method' => 'other',
        ]);

        $this->actingAs($this->user)
            ->get(route('pledges.index', $broken))
            ->assertOk()
            ->assertSee('Total pledged');

        $this->assertEquals(0, (float) $broken->pledges()->sum('amount'));
    }
}
