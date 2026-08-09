<?php

namespace Tests\Feature;

use App\Models\Pledge;
use App\Models\User;
use App\Models\Wedding;
use App\Services\PledgeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\XlsxBuilder;
use Tests\TestCase;

/**
 * Excel files are what people actually have, so the importer reads .xlsx
 * directly rather than making them convert to CSV first.
 */
class XlsxImportTest extends TestCase
{
    use RefreshDatabase;

    private Wedding $wedding;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Owner',
            'email' => 'xlsx@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->wedding = Wedding::create([
            'user_id' => $user->id,
            'partner_a' => 'Asha',
            'partner_b' => 'Juma',
            'slug' => 'xlsx-test',
            'currency' => 'TZS',
        ]);

        $this->path = tempnam(sys_get_temp_dir(), 'sheet').'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_it_imports_a_real_xlsx_file(): void
    {
        XlsxBuilder::create($this->path, [
            ['name', 'pledge', 'email', 'phone', 'amount', 'contribution'],
            ['Asha Mwinyi', 'Cash gift', 'asha@example.com', '0712345678', '500000', '200000'],
            ['Juma Ally', 'Furniture', 'juma@example.com', '0754111222', '300000', ''],
        ]);

        $importer = new PledgeImporter($this->wedding);
        $result = $importer->preview($this->path);
        $stats = $importer->import($result['rows']);

        $this->assertCount(2, $result['rows']);
        $this->assertSame(2, $stats['created']);
        $this->assertSame(1, $stats['contributions']);

        $asha = Pledge::where('pledger_name', 'Asha Mwinyi')->first();
        $this->assertEquals(500000, (float) $asha->amount);
        $this->assertSame('asha@example.com', $asha->pledger_email);
        $this->assertSame('partial', $asha->status);
    }

    public function test_it_reads_excel_date_cells(): void
    {
        // Dates are stored as serial numbers; without style handling this
        // would import as something like 46235 rather than a real date.
        XlsxBuilder::create($this->path, [
            ['name', 'amount', 'date'],
            ['Asha Mwinyi', '500000', '2026-08-01'],
        ], [2 => true]);

        $result = (new PledgeImporter($this->wedding))->preview($this->path);

        $this->assertSame('2026-08-01', $result['rows'][0]['date']);
    }

    public function test_it_handles_the_users_sheet_shape_in_xlsx(): void
    {
        XlsxBuilder::create($this->path, [
            ['name', 'pledge', 'contribution'],
            ['Adv. Jasper', '2500000', '1000000'],
            ['Mr & Mrs Harry Sabuni', '1500000', ''],
        ]);

        $importer = new PledgeImporter($this->wedding);
        $stats = $importer->import($importer->preview($this->path)['rows']);

        $this->assertSame(2, $stats['created']);

        $jasper = Pledge::where('pledger_name', 'Adv. Jasper')->first();
        $this->assertEquals(2500000, (float) $jasper->amount);
        $this->assertSame('partial', $jasper->status);
        $this->assertNull($jasper->notes);
    }

    public function test_it_skips_blank_spreadsheet_rows(): void
    {
        XlsxBuilder::create($this->path, [
            ['name', 'amount'],
            ['Asha', '500000'],
            ['', ''],
            ['Juma', '300000'],
        ]);

        $result = (new PledgeImporter($this->wedding))->preview($this->path);

        $this->assertCount(2, $result['rows'], 'Empty spreadsheet rows must not become pledges.');
    }

    public function test_reimporting_an_xlsx_updates_rather_than_duplicates(): void
    {
        XlsxBuilder::create($this->path, [
            ['name', 'email', 'amount'],
            ['Asha Mwinyi', 'asha@example.com', '500000'],
        ]);

        $importer = new PledgeImporter($this->wedding);
        $rows = $importer->preview($this->path)['rows'];
        $importer->import($rows);

        $again = (new PledgeImporter($this->wedding))->import($rows);

        $this->assertSame(1, $again['updated']);
        $this->assertSame(1, Pledge::count());
    }

    public function test_it_uploads_an_xlsx_through_the_web_form(): void
    {
        XlsxBuilder::create($this->path, [
            ['name', 'amount'],
            ['Asha Mwinyi', '500000'],
        ]);

        $upload = new UploadedFile($this->path, 'pledges.xlsx', null, null, true);

        $response = $this->actingAs($this->wedding->user)
            ->post(route('pledges.import.preview', $this->wedding), ['file' => $upload]);

        $response->assertOk();
        $response->assertSee('Asha Mwinyi');
        $this->assertSame(0, Pledge::count(), 'Preview must not write.');

        $this->actingAs($this->wedding->user)
            ->post(route('pledges.import.store', $this->wedding))
            ->assertRedirect(route('pledges.index', $this->wedding));

        $this->assertSame(1, Pledge::count());
    }

    public function test_a_corrupt_file_fails_gracefully(): void
    {
        file_put_contents($this->path, 'PK this is not really a spreadsheet');

        $result = (new PledgeImporter($this->wedding))->preview($this->path);

        $this->assertSame([], $result['rows']);
        $this->assertNotEmpty($result['errors'], 'A broken file must report an error, not crash.');
    }
}
