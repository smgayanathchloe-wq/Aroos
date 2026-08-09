<?php

namespace Tests\Feature;

use App\Models\Pledge;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Covers the HTTP layer: upload -> preview -> commit, which the service-level
 * tests do not exercise (routing, validation, session hand-off, auth).
 */
class PledgeImportHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeWedding(string $email, string $slug): array
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $wedding = Wedding::create([
            'user_id' => $user->id,
            'partner_a' => 'Asha',
            'partner_b' => 'Juma',
            'slug' => $slug,
            'currency' => 'TZS',
        ]);

        return [$user, $wedding];
    }

    public function test_full_upload_preview_and_commit_flow(): void
    {
        [$user, $wedding] = $this->makeWedding('owner1@example.com', 'a-b-1');

        $csv = "name,pledge,email,phone,amount,date,contribution\n"
            ."Asha Mwinyi,Cash,asha@example.com,0712345678,500000,2026-08-01,200000\n";

        $preview = $this->actingAs($user)->post(
            route('pledges.import.preview', $wedding),
            ['file' => UploadedFile::fake()->createWithContent('pledges.csv', $csv)]
        );

        $preview->assertOk();
        $preview->assertSee('Asha Mwinyi');
        $this->assertSame(0, Pledge::count(), 'Preview must not write anything.');

        $commit = $this->actingAs($user)->post(route('pledges.import.store', $wedding));

        $commit->assertRedirect(route('pledges.index', $wedding));
        $this->assertSame(1, Pledge::count());
        $this->assertSame('partial', Pledge::first()->status);
    }

    public function test_commit_without_a_preview_is_rejected(): void
    {
        [$user, $wedding] = $this->makeWedding('owner2@example.com', 'a-b-2');

        $this->actingAs($user)
            ->post(route('pledges.import.store', $wedding))
            ->assertRedirect(route('pledges.import.index', $wedding));

        $this->assertSame(0, Pledge::count());
    }

    public function test_template_downloads_as_csv(): void
    {
        [$user, $wedding] = $this->makeWedding('owner3@example.com', 'a-b-3');

        $response = $this->actingAs($user)
            ->get(route('pledges.import.template', $wedding))
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $response->assertSee('name,pledge,email,phone,amount,date,contribution', false);
    }

    public function test_it_rejects_a_non_csv_upload(): void
    {
        [$user, $wedding] = $this->makeWedding('owner4@example.com', 'a-b-4');

        $this->actingAs($user)
            ->post(route('pledges.import.preview', $wedding), [
                'file' => UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_guests_cannot_import(): void
    {
        [, $wedding] = $this->makeWedding('owner5@example.com', 'a-b-5');

        $this->post(route('pledges.import.preview', $wedding), [
            'file' => UploadedFile::fake()->createWithContent('p.csv', "name,amount\nX,1\n"),
        ])->assertRedirect();

        $this->assertSame(0, Pledge::count());
    }
}
