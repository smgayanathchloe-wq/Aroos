<?php

namespace Database\Seeders;

use App\Marketplace\BookingService;
use App\Marketplace\NegotiationService;
use App\Marketplace\ProviderStats;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\TzRegion;
use App\Models\User;
use App\Models\Wedding;
use App\Services\ChecklistSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo data for the whole system: one wedding (tenant) with its planner and
 * couple, guests, budget, pledges in every state, AI outreach settings, and
 * marketplace providers with a live negotiation and a confirmed booking.
 *
 *   php artisan db:seed --class=RoleUsersSeeder
 *
 * Passwords are never stored in code. New accounts get DEMO_PASSWORD from the
 * environment, or a random one printed once at the end. Existing accounts are
 * left exactly as they are (their passwords are never reset). Refuses to run in
 * production unless DEMO_SEED_ALLOW_PRODUCTION=true.
 */
class RoleUsersSeeder extends Seeder
{
    private string $password;

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $accounts = [];

    public function run(): void
    {
        if (app()->environment('production') && ! env('DEMO_SEED_ALLOW_PRODUCTION')) {
            $this->command?->error('Refusing to create demo accounts in production. Set DEMO_SEED_ALLOW_PRODUCTION=true to override.');

            return;
        }
        $this->password = (string) (env('DEMO_PASSWORD') ?: Str::password(16, symbols: false));
        $this->call(MarketplaceSeeder::class);

        $admin = $this->account('admin@wed.letsventure.co.tz', 'Super Admin', User::ROLE_SUPER_ADMIN, 'Super admin', ['is_admin' => true]);
        $planner = $this->account('planner@wed.letsventure.co.tz', 'Neema Planner', User::ROLE_PLANNER, 'Planner (wedding owner)');
        $couple = $this->account('couple@wed.letsventure.co.tz', 'Amina & Joseph', User::ROLE_COUPLE, 'Couple (view-only on wedding, can book services)');

        // Sample wedding owned by the planner, shared with the couple (view-only).
        $wedding = Wedding::firstOrCreate(
            ['slug' => 'amina-joseph'],
            [
                'user_id' => $planner->id,
                'partner_a' => 'Amina',
                'partner_b' => 'Joseph',
                'wedding_date' => now()->addMonths(5)->startOfDay(),
                'wedding_time' => '14:00',
                'venue_name' => 'Serena Ballroom',
                'venue_city' => 'Dar es Salaam',
                'venue_country' => 'Tanzania',
                'currency' => 'TZS',
                'total_budget' => 45000000,
                'story' => "We met at a friend's graduation in 2019. Six years, two cities and a thousand cups of chai later, Joseph asked — and Amina said yes.",
            ]
        );

        if ($wedding->wasRecentlyCreated) {
            ChecklistSeeder::seed($wedding);

            $wedding->websiteSetting()->create([
                'theme' => 'atelier',
                'published' => true,
                'sections' => ['hero', 'story', 'details', 'timeline', 'rsvp'],
                'welcome_message' => 'Together with their families, they invite you to celebrate their union.',
            ]);

            foreach ([
                ['Venue', 12000000, 12000000],
                ['Catering', 10000000, 4000000],
                ['Photography & Video', 5000000, 1500000],
                ['Attire & Beauty', 4500000, 2000000],
                ['Music & Entertainment', 3000000, 0],
                ['Decor & Flowers', 4000000, 800000],
                ['Transport', 2000000, 0],
            ] as [$name, $budgeted, $spent]) {
                $wedding->budgetCategories()->create(compact('name', 'budgeted', 'spent'));
            }

            $tables = [];
            foreach (range(1, 6) as $i) {
                $tables[] = $wedding->seatingTables()->create(['name' => 'Table '.$i, 'capacity' => 10, 'shape' => 'round']);
            }

            $guests = [
                ['Fatma', 'Hassan', 'fatma@example.com', '+255700000001', 2, 'attending'],
                ['John', 'Mushi', 'john@example.com', '+255700000002', 1, 'attending'],
                ['Grace', 'Kimaro', 'grace@example.com', '+255700000003', 3, 'pending'],
                ['Peter', 'Mwakalinga', 'peter@example.com', '+255700000004', 2, 'maybe'],
                ['Zainab', 'Said', 'zainab@example.com', '+255700000005', 1, 'attending'],
                ['David', 'Komba', 'david@example.com', '+255700000006', 2, 'pending'],
                ['Rehema', 'Njau', 'rehema@example.com', '+255700000007', 1, 'declined'],
            ];
            foreach ($guests as $i => [$fn, $ln, $email, $phone, $party, $status]) {
                $wedding->guests()->create([
                    'first_name' => $fn,
                    'last_name' => $ln,
                    'email' => $email,
                    'phone' => $phone,
                    'party_count' => $party,
                    'rsvp_status' => $status,
                    'seating_table_id' => $status === 'attending' ? $tables[$i % 3]->id : null,
                ]);
            }

            foreach ([
                ['Catering', 'Mama Ntilie Catering Co.', 'booked', 10000000, 5],
                ['Photography', 'Golden Hour Studios', 'booked', 5000000, 5],
                ['Music', 'DJ Flava', 'quote', 1800000, 4],
                ['Decor', 'Blossom Events', 'contacted', 4000000, null],
            ] as [$cat, $name, $status, $price, $rating]) {
                $wedding->vendors()->create([
                    'category' => $cat,
                    'name' => $name,
                    'status' => $status,
                    'price_estimate' => $price,
                    'rating' => $rating,
                ]);
            }

            foreach ([
                ['Getting ready', '08:00', 120, 'Home'],
                ['Church ceremony', '11:00', 90, 'St. Joseph Cathedral'],
                ['Photo session', '13:00', 90, 'Botanical Gardens'],
                ['Guests arrive & cocktails', '15:30', 60, 'Serena Ballroom'],
                ['Grand entrance', '16:30', 30, 'Serena Ballroom'],
                ['Dinner service', '17:00', 90, 'Serena Ballroom'],
                ['Speeches & toasts', '18:30', 60, 'Serena Ballroom'],
                ['Cake cutting', '19:30', 30, 'Serena Ballroom'],
                ['First dance & party', '20:00', 180, 'Serena Ballroom'],
            ] as [$title, $time, $dur, $loc]) {
                $wedding->timelineEvents()->create([
                    'title' => $title,
                    'start_time' => $time,
                    'duration_minutes' => $dur,
                    'location' => $loc,
                    'public' => true,
                ]);
            }

            $g1 = $wedding->guests()->first();
            $p1 = $wedding->pledges()->create([
                'pledger_name' => 'Uncle Ibrahim',
                'pledger_email' => 'ibrahim@example.com',
                'pledger_phone' => '+255700000010',
                'amount' => 2000000,
                'due_date' => now()->addMonths(2),
            ]);
            $wedding->pledges()->create([
                'guest_id' => $g1?->id,
                'pledger_name' => 'Fatma Hassan',
                'pledger_email' => 'fatma@example.com',
                'amount' => 500000,
                'due_date' => now()->addMonth(),
            ]);
            $wedding->contributions()->create([
                'pledge_id' => $p1->id,
                'contributor_name' => 'Uncle Ibrahim',
                'amount' => 1000000,
                'method' => 'mpesa',
                'reference' => 'MP2607301122',
                'received_on' => now(),
            ]);
            $p1->refreshStatus();

            $wedding->reminders()->create([
                'name' => 'Pledge balance reminder',
                'stage' => 'pledge_reminder',
                'channel' => 'both',
                'target_pledge_statuses' => ['pledged', 'partial'],
                'subject' => 'A gentle reminder from {couple}',
                'body' => "Dear {name},\n\nThank you for your pledge of {currency} {amount} towards {couple}'s wedding on {date}. Your remaining balance is {currency} {balance}.\n\nYou can contribute via M-Pesa or bank transfer. Every contribution brings the day closer!\n\nWith gratitude,\n{couple}",
            ]);

            $wedding->reminders()->create([
                'name' => 'RSVP follow-up',
                'stage' => 'rsvp_followup',
                'channel' => 'email',
                'target_rsvp_statuses' => ['pending', 'maybe'],
                'subject' => "Will you join {couple} on {date}?",
                'body' => "Dear {name},\n\nWe haven't received your RSVP yet for {couple}'s wedding on {date} at {venue}. Kindly respond here: {rsvp_link}\n\nWe hope to celebrate with you!",
            ]);

            // Couple gets view-only collaborator access.
            $wedding->collaborators()->create([
                'user_id' => $couple->id,
                'email' => $couple->email,
                'role' => 'partner',
                'status' => 'active',
            ]);
        }

        $this->outreach($wedding);
        $this->marketplace($wedding, $planner, $couple);

        $this->command?->newLine();
        $this->command?->info('Demo wedding: '.$wedding->coupleNames().' — sign in at '.url('/login'));
        $this->command?->table(['Role', 'Email', 'Password'], $this->accounts);
        $this->command?->warn('Store these passwords now; they are not saved anywhere else. Existing accounts keep their own passwords.');
    }

    /** Creates the account once; never touches an existing account's password. */
    private function account(string $email, string $name, string $role, string $label, array $extra = []): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make($this->password),
            'role' => $role,
            'phone' => '+2557'.random_int(10000000, 99999999),
        ] + $extra);
        $this->accounts[] = [$label, $email, $user->wasRecentlyCreated ? $this->password : '(existing — unchanged)'];

        return $user;
    }

    /** Pledges in every outreach state plus Swahili AI-outreach settings (switched off until credentials are added). */
    private function outreach(Wedding $wedding): void
    {
        foreach ([
            ['Mzee Salim Juma', '+255712000101', 0, 0],          // not pledged yet: the AI invites them
            ['Bi. Mwanaisha Ali', '+255712000102', 300000, 0],   // pledged, nothing paid
            ['Kaka Hamisi Mrisho', '+255712000103', 1000000, 400000], // partly paid
            ['Dada Upendo Mollel', '+255712000104', 250000, 250000],  // fully paid: thanked, never asked again
        ] as [$name, $phone, $amount, $paid]) {
            $pledge = $wedding->pledges()->firstOrCreate(['pledger_name' => $name], [
                'pledger_phone' => $phone,
                'amount' => $amount,
                'outreach_consent' => true,
                'language' => 'sw',
            ]);
            if ($pledge->wasRecentlyCreated && $paid > 0) {
                $wedding->contributions()->create([
                    'pledge_id' => $pledge->id, 'contributor_name' => $name, 'amount' => $paid,
                    'method' => 'mpesa', 'reference' => 'DEMO'.$pledge->id, 'received_on' => now(),
                ]);
            }
            $pledge->refreshStatus();
        }

        if ($wedding->outreach_config === null) {
            $wedding->update(['outreach_config' => [
                'enabled' => false, // add credentials in AI Outreach → Settings, then switch on
                'branding' => ['assistant_name' => 'Amani', 'org_name' => 'Kamati ya Harusi ya Amina na Joseph', 'language' => 'sw'],
                'wedding' => [
                    'dress_code' => 'Vazi rasmi (formal)',
                    'contact_person_name' => 'Neema',
                    'gift_policy' => 'Michango kupitia M-Pesa au benki inakaribishwa.',
                    'extra_faqs' => [
                        ['q' => 'Kuna maegesho?', 'a' => 'Ndiyo, maegesho ni bure ukumbini.'],
                        ['q' => 'Watoto wanaruhusiwa?', 'a' => 'Ndiyo, watoto wanakaribishwa.'],
                    ],
                ],
            ]]);
        }
    }

    /** Verified providers, a pending one for the admin queue, a live negotiation and a confirmed booking. */
    private function marketplace(Wedding $wedding, User $planner, User $couple): void
    {
        $dar = TzRegion::where('name', 'Dar es Salaam')->first();
        $category = fn (string $slug) => ServiceCategory::where('slug', $slug)->firstOrFail();

        $lens = $this->provider(
            $this->account('photographer@wed.letsventure.co.tz', 'Baraka Mushi', User::ROLE_PLANNER, 'Provider owner (photography)'),
            'Baraka Lens Studio', $category('photography-videography'), $dar, true,
            'Wedding photography and cinematography across Dar es Salaam — traditional, church and reception coverage.',
            ['title' => 'Wedding photography & video', 'pricing_type' => 'packages', 'is_negotiable' => true, 'scheduling' => 'per_day', 'capacity_per_day' => 2,
                'inclusions' => ['Edited high-resolution photos', 'Online gallery'], 'exclusions' => ['Printed album (see Premium)']],
            [
                ['name' => 'Basic', 'price' => 500000, 'features' => ['4 hours coverage', 'One photographer', '150 edited photos'], 'deposit_percent' => 30],
                ['name' => 'Standard', 'price' => 900000, 'features' => ['8 hours coverage', 'Two photographers', 'Highlight video'], 'deposit_percent' => 30],
                ['name' => 'Premium', 'price' => 1500000, 'features' => ['Full day', 'Drone coverage', 'Premium album'], 'extras' => [['name' => 'Same-day edit', 'price' => 300000]], 'deposit_percent' => 40],
            ],
        );
        $feast = $this->provider(
            $this->account('caterer@wed.letsventure.co.tz', 'Zawadi Komba', User::ROLE_PLANNER, 'Provider owner (catering)'),
            'Zawadi Feast Catering', $category('catering'), $dar, true,
            'Swahili and continental menus for 100 to 1,000 guests, with service staff.',
            ['title' => 'Wedding catering', 'pricing_type' => 'quote_only', 'scheduling' => 'per_day', 'capacity_per_day' => 3, 'capacity_note' => 'Up to 1,000 guests'],
            [],
        );
        $this->provider(
            $this->account('decor@wed.letsventure.co.tz', 'Rehema Mwangaza', User::ROLE_PLANNER, 'Provider owner (decor, awaiting verification)'),
            'Mwangaza Decor', $category('decoration-event-design'), $dar, false,
            'Stage, table and venue styling.',
            ['title' => 'Venue decoration', 'pricing_type' => 'fixed', 'base_price' => 2500000, 'scheduling' => 'per_day', 'capacity_per_day' => 1],
            [],
        );

        $staff = $this->account('staff@wed.letsventure.co.tz', 'Juma Assistant', User::ROLE_PLANNER, 'Provider staff (Baraka Lens: requests + replies)');
        $lens->members()->firstOrCreate(['user_id' => $staff->id], ['email' => $staff->email, 'role' => 'staff', 'permissions' => ['enquiries', 'respond'], 'status' => 'active']);

        if ($wedding->hasMany(\App\Models\Marketplace\ServiceEnquiry::class)->exists()) {
            return;
        }
        $negotiation = app(NegotiationService::class);
        $eventDate = ($wedding->wedding_date ?? now()->addMonths(5))->toDateString();

        // Live negotiation: provider's latest offer is waiting for the wedding to accept.
        $service = $lens->services()->first();
        $enquiry = $negotiation->createEnquiry($planner, $wedding, $lens, [
            'service_id' => $service->id, 'package_id' => $service->packages()->where('name', 'Standard')->value('id'),
            'event_date' => $eventDate, 'event_start' => '10:00', 'event_end' => '22:00', 'guests' => 300, 'budget' => 1000000,
            'requirements' => 'Church ceremony and reception, photo and video.', 'preferred_contact' => 'phone',
        ]);
        $negotiation->quote($enquiry, $lens->owner, ['amount' => 900000, 'message' => 'Standard package, full coverage of both venues.']);
        $negotiation->counter($enquiry, $couple, ['amount' => 800000, 'message' => 'Our photography budget is 800,000.']);
        $negotiation->quote($enquiry, $lens->owner, ['amount' => 850000, 'message' => 'We can meet you at 850,000.']);

        // Confirmed booking (also appears in the wedding's Vendors and Budget).
        $catering = $negotiation->createEnquiry($couple, $wedding, $feast, [
            'service_id' => $feast->services()->value('id'), 'event_date' => $eventDate, 'guests' => 300,
            'requirements' => 'Buffet dinner for 300, Swahili menu.', 'preferred_contact' => 'whatsapp',
        ]);
        $negotiation->quote($catering, $feast->owner, ['amount' => 6000000, 'message' => 'Buffet for 300 including service staff.']);
        app(BookingService::class)->accept($catering, $catering->latestVersion()->id, $planner);
    }

    private function provider(User $owner, string $name, ServiceCategory $category, ?TzRegion $region, bool $verified, string $description, array $service, array $packages): ProviderProfile
    {
        $provider = ProviderProfile::firstOrCreate(['owner_user_id' => $owner->id], [
            'business_name' => $name, 'description' => $description, 'primary_category_id' => $category->id,
            'contact_name' => $owner->name, 'phone' => $owner->phone, 'whatsapp' => $owner->phone, 'email' => $owner->email,
            'region_id' => $region?->id, 'city' => 'Dar es Salaam', 'status' => 'active',
            'verification_status' => $verified ? 'verified' : 'submitted', 'verified_at' => $verified ? now() : null,
            'social_links' => ['instagram' => 'https://www.instagram.com/'.Str::slug($name, '')],
            'operating_hours' => ['text' => 'Mon–Sat 8:00–18:00'],
        ]);
        if ($provider->wasRecentlyCreated) {
            $provider->categories()->attach($category->id);
            $provider->members()->create(['user_id' => $owner->id, 'email' => $owner->email, 'role' => 'owner']);
            $created = $provider->services()->create($service + ['category_id' => $category->id, 'status' => 'published', 'capacity_scope' => 'service']);
            foreach ($packages as $i => $pkg) {
                $created->packages()->create($pkg + ['sort' => $i]);
            }
            ProviderStats::refresh($provider);
        }

        return $provider->load('members');
    }
}
