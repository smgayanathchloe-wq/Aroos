<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wedding;
use App\Services\ChecklistSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleUsersSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@wed.letsventure.co.tz'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin@2026'),
                'role' => User::ROLE_SUPER_ADMIN,
                'is_admin' => true,
            ]
        );

        $planner = User::updateOrCreate(
            ['email' => 'planner@wed.letsventure.co.tz'],
            [
                'name' => 'Neema Planner',
                'password' => Hash::make('Planner@2026'),
                'role' => User::ROLE_PLANNER,
            ]
        );

        $couple = User::updateOrCreate(
            ['email' => 'couple@wed.letsventure.co.tz'],
            [
                'name' => 'Amina & Joseph',
                'password' => Hash::make('Couple@2026'),
                'role' => User::ROLE_COUPLE,
            ]
        );

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
    }
}
