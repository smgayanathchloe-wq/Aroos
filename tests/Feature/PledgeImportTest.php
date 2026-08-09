<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Pledge;
use App\Models\User;
use App\Models\Wedding;
use App\Services\PledgeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PledgeImportTest extends TestCase
{
    use RefreshDatabase;

    private Wedding $wedding;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Test Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->wedding = Wedding::create([
            'user_id' => $user->id,
            'partner_a' => 'Asha',
            'partner_b' => 'Juma',
            'slug' => 'asha-juma',
            'currency' => 'TZS',
        ]);
    }

    private function importCsv(string $csv): array
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        file_put_contents($path, $csv);

        $importer = new PledgeImporter($this->wedding);
        $result = $importer->preview($path);
        $stats = $importer->import($result['rows']);

        unlink($path);

        return [$result, $stats];
    }

    public function test_it_imports_new_pledges_and_contributions(): void
    {
        [, $stats] = $this->importCsv(
            "name,pledge,email,phone,amount,date,contribution\n".
            "Asha Mwinyi,Cash gift,asha@example.com,+255712345678,500000,2026-08-01,200000\n".
            "Juma Ally,Furniture,juma@example.com,0754111222,300000,2026-08-02,0\n"
        );

        $this->assertSame(2, $stats['created']);
        $this->assertSame(1, $stats['contributions']);
        $this->assertSame(2, Pledge::count());

        $asha = Pledge::where('pledger_name', 'Asha Mwinyi')->first();
        $this->assertEquals(500000, (float) $asha->amount);
        $this->assertSame('2026-08-01', $asha->due_date->toDateString());
        $this->assertSame('Cash gift', $asha->notes);
        // 200k of 500k paid, so the pledge is partially fulfilled.
        $this->assertSame('partial', $asha->status);

        $juma = Pledge::where('pledger_name', 'Juma Ally')->first();
        $this->assertSame('pledged', $juma->status);
    }

    public function test_reimporting_updates_instead_of_duplicating(): void
    {
        $csv = "name,email,amount,contribution\n".
               "Asha Mwinyi,asha@example.com,500000,200000\n";

        $this->importCsv($csv);

        // Same person, revised figures.
        [, $stats] = $this->importCsv(
            "name,email,amount,contribution\n".
            "Asha Mwinyi,asha@example.com,600000,450000\n"
        );

        $this->assertSame(1, $stats['updated']);
        $this->assertSame(0, $stats['created']);
        $this->assertSame(1, Pledge::count(), 'Re-import must not duplicate the pledge.');

        $pledge = Pledge::first();
        $this->assertEquals(600000, (float) $pledge->amount);

        // Critically, the contribution is adjusted rather than stacked, so the
        // collected total reflects reality instead of 200k + 450k.
        $this->assertSame(1, Contribution::count(), 'Re-import must not stack contributions.');
        $this->assertEquals(450000, (float) Contribution::first()->amount);
        $this->assertSame('partial', $pledge->status);
    }

    public function test_it_matches_on_phone_when_email_missing(): void
    {
        $this->importCsv("name,phone,amount\nAsha Mwinyi,+255712345678,500000\n");
        [, $stats] = $this->importCsv("name,phone,amount\nAsha M,+255712345678,700000\n");

        $this->assertSame(1, $stats['updated']);
        $this->assertSame(1, Pledge::count());
        $this->assertEquals(700000, (float) Pledge::first()->amount);
    }

    public function test_it_matches_on_name_only_when_no_contact_details(): void
    {
        $this->importCsv("name,amount\nBibi Mariam,100000\n");
        [, $stats] = $this->importCsv("name,amount\nbibi mariam,150000\n");

        $this->assertSame(1, $stats['updated'], 'Name match should be case-insensitive.');
        $this->assertSame(1, Pledge::count());
    }

    public function test_it_marks_pledge_fulfilled_when_fully_paid(): void
    {
        $this->importCsv("name,email,amount,contribution\nAsha,asha@example.com,500000,500000\n");

        $this->assertSame('fulfilled', Pledge::first()->status);
    }

    public function test_it_parses_messy_spreadsheet_values(): void
    {
        [$result] = $this->importCsv(
            "Full Name,Mobile,Amount Pledged,Paid,Pledge Date\n".
            '"Asha Mwinyi","0712 345 678","TZS 1,500,000.50","250,000","01/08/2026"'."\n"
        );

        $row = $result['rows'][0];
        $this->assertSame('Asha Mwinyi', $row['name']);
        $this->assertSame('0712345678', $row['phone']);
        $this->assertSame(1500000.50, $row['amount'], 'Currency symbols and thousands separators must be stripped.');
        $this->assertSame(250000.0, $row['contribution']);
        $this->assertSame('2026-08-01', $row['date'], 'Day-first dates must not be read as month-first.');
    }

    public function test_it_recognises_alternative_header_names(): void
    {
        [$result] = $this->importCsv(
            "Pledger Name,E-Mail,Telephone,Kiasi,Mchango\n".
            "Asha,asha@example.com,0712345678,500000,100000\n"
        );

        $row = $result['rows'][0];
        $this->assertSame('Asha', $row['name']);
        $this->assertSame('asha@example.com', $row['email']);
        $this->assertSame(500000.0, $row['amount']);
        $this->assertSame(100000.0, $row['contribution']);
    }

    public function test_a_numeric_pledge_column_is_read_as_the_amount(): void
    {
        // Real sheets often put the figure in the "pledge" column and have no
        // separate amount column at all. Without this, every pledge imports
        // as zero and the money ends up stranded in the notes field.
        [$result, $stats] = $this->importCsv(
            "name,pledge\n".
            "Mr & Mrs Harry Sabuni,\"1,500,000\"\n".
            "Ms. Natasha Sabuni,150000\n"
        );

        $this->assertSame(1500000.0, $result['rows'][0]['amount']);
        $this->assertSame('', $result['rows'][0]['pledge'], 'The figure must not also be kept as a note.');
        $this->assertSame(2, $stats['created']);

        $pledge = Pledge::where('pledger_name', 'Mr & Mrs Harry Sabuni')->first();
        $this->assertEquals(1500000, (float) $pledge->amount);
        $this->assertNull($pledge->notes);
    }

    public function test_a_descriptive_pledge_column_is_still_kept_as_notes(): void
    {
        [$result] = $this->importCsv(
            "name,pledge,amount\n".
            "Asha,Sofa set,500000\n"
        );

        $this->assertSame('Sofa set', $result['rows'][0]['pledge']);
        $this->assertSame(500000.0, $result['rows'][0]['amount']);
    }

    public function test_an_explicit_amount_column_wins_over_the_pledge_column(): void
    {
        [$result] = $this->importCsv(
            "name,pledge,amount\n".
            "Asha,999,500000\n"
        );

        $this->assertSame(500000.0, $result['rows'][0]['amount'], 'A real amount column must take precedence.');
        $this->assertSame('999', $result['rows'][0]['pledge']);
    }

    public function test_it_imports_the_users_real_sheet_shape(): void
    {
        // Mirrors the layout actually seen in the database: names, the pledged
        // figure under "pledge", and only some rows paid.
        [, $stats] = $this->importCsv(
            "name,pledge,contribution\n".
            "Adv. Jasper,1000000,1000000\n".
            "Mr. & Mrs. Stephen Mgaya,50000,50000\n".
            "Mr & Mrs Harry Sabuni,1500000,\n"
        );

        $this->assertSame(3, $stats['created']);

        $paid = Pledge::where('pledger_name', 'Adv. Jasper')->first();
        $this->assertEquals(1000000, (float) $paid->amount);
        $this->assertSame('fulfilled', $paid->status);

        $unpaid = Pledge::where('pledger_name', 'Mr & Mrs Harry Sabuni')->first();
        $this->assertEquals(1500000, (float) $unpaid->amount);
        $this->assertSame('pledged', $unpaid->status, 'An unpaid pledge must not read as fulfilled.');
    }

    public function test_a_paid_row_is_not_marked_fulfilled_when_the_amount_is_unknown(): void
    {
        // Regression: when the pledged figure failed to parse, amount stayed 0
        // and any payment made the pledge look fully settled. Six real pledges
        // were wrongly reading as 'fulfilled' while still owing money.
        [, $stats] = $this->importCsv(
            "name,pledge,contribution\n".
            "Adv. Jasper,2500000,1000000\n"
        );

        $this->assertSame(1, $stats['created']);

        $pledge = Pledge::first();
        $this->assertEquals(2500000, (float) $pledge->amount);
        $this->assertSame('partial', $pledge->status, 'Owing 1.5m must not read as fulfilled.');
        $this->assertEqualsWithDelta(1500000.0, $pledge->balance(), 0.01);
    }

    public function test_reimport_clears_a_figure_left_stranded_in_notes(): void
    {
        // Simulates rows created by the older importer: amount 0, with the
        // pledged figure sitting in notes. Re-uploading the same file must
        // both fix the amount and tidy the leftover note.
        $pledge = $this->wedding->pledges()->create([
            'pledger_name' => 'Adv. Jasper',
            'amount' => 0,
            'notes' => '2500000',
            'status' => 'pledged',
        ]);

        $this->importCsv("name,pledge\nAdv. Jasper,2500000\n");

        $pledge->refresh();
        $this->assertEquals(2500000, (float) $pledge->amount);
        $this->assertNull($pledge->notes, 'The stranded figure must not linger as a note.');
        $this->assertSame(1, Pledge::count());
    }

    public function test_reimport_keeps_a_real_note_that_is_not_a_number(): void
    {
        $pledge = $this->wedding->pledges()->create([
            'pledger_name' => 'Adv. Jasper',
            'amount' => 0,
            'notes' => 'Paying after the harvest',
            'status' => 'pledged',
        ]);

        $this->importCsv("name,pledge\nAdv. Jasper,2500000\n");

        $pledge->refresh();
        $this->assertEquals(2500000, (float) $pledge->amount);
        $this->assertSame('Paying after the harvest', $pledge->notes, 'A genuine remark must survive.');
    }

    public function test_it_matches_names_containing_quotes(): void
    {
        // Real pledger names include nicknames in quotes. Re-importing must
        // update them, not create a second copy.
        $csv = "name,pledge\n".'"Mr. Victor ""Bumija"" Muze","300000"'."\n";

        $this->importCsv($csv);
        [$result, $stats] = $this->importCsv($csv);

        $this->assertSame('Mr. Victor "Bumija" Muze', $result['rows'][0]['name']);
        $this->assertSame(1, $stats['updated']);
        $this->assertSame(1, Pledge::count(), 'A quoted name must not duplicate on re-import.');
    }

    public function test_it_skips_rows_without_a_name(): void
    {
        [$result, $stats] = $this->importCsv(
            "name,amount\n".
            "Asha,500000\n".
            ",300000\n".
            "\n".
            "Juma,200000\n"
        );

        $this->assertCount(2, $result['rows']);
        $this->assertSame(2, $stats['created']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_it_handles_semicolon_delimited_files(): void
    {
        [$result] = $this->importCsv("name;email;amount\nAsha;asha@example.com;500000\n");

        $this->assertCount(1, $result['rows']);
        $this->assertSame(500000.0, $result['rows'][0]['amount']);
    }

    public function test_it_ignores_a_utf8_bom(): void
    {
        [$result] = $this->importCsv("\xEF\xBB\xBFname,amount\nAsha,500000\n");

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Asha', $result['rows'][0]['name'], 'A BOM must not break the first header.');
    }

    public function test_sparse_reimport_does_not_blank_existing_data(): void
    {
        $this->importCsv("name,email,phone,amount\nAsha,asha@example.com,0712345678,500000\n");

        // A later file that only carries the amount must not wipe the phone.
        $this->importCsv("name,email,amount\nAsha,asha@example.com,600000\n");

        $pledge = Pledge::first();
        $this->assertSame('0712345678', $pledge->pledger_phone);
        $this->assertEquals(600000, (float) $pledge->amount);
    }

    public function test_import_is_scoped_to_one_wedding(): void
    {
        $other = Wedding::create([
            'user_id' => $this->wedding->user_id,
            'partner_a' => 'X',
            'partner_b' => 'Y',
            'slug' => 'x-y',
            'currency' => 'TZS',
        ]);

        $this->importCsv("name,email,amount\nAsha,asha@example.com,500000\n");

        // The same person in a different wedding is a separate pledge.
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        file_put_contents($path, "name,email,amount\nAsha,asha@example.com,900000\n");
        $importer = new PledgeImporter($other);
        $stats = $importer->import($importer->preview($path)['rows']);
        unlink($path);

        $this->assertSame(1, $stats['created'], 'Must not update another wedding\'s pledge.');
        $this->assertSame(1, Pledge::where('wedding_id', $this->wedding->id)->count());
        $this->assertSame(1, Pledge::where('wedding_id', $other->id)->count());
    }
}
