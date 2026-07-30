<?php

namespace App\Services;

use App\Models\Wedding;

class ChecklistSeeder
{
    public static function seed(Wedding $wedding): void
    {
        $phases = [
            '12+ Months Before' => [
                'Set your budget and vision',
                'Draft the guest list',
                'Choose a wedding date',
                'Book the ceremony & reception venue',
                'Hire a wedding planner (optional)',
            ],
            '9-12 Months Before' => [
                'Book photographer & videographer',
                'Book caterer and schedule tastings',
                'Book band or DJ',
                'Start dress / attire shopping',
                'Create your wedding website',
            ],
            '6-9 Months Before' => [
                'Send save-the-dates',
                'Book florist',
                'Book transportation',
                'Reserve hotel room blocks',
                'Order invitations',
                'Launch pledge & contribution drive',
            ],
            '3-6 Months Before' => [
                'Order the cake',
                'Choose wedding rings',
                'Plan the honeymoon',
                'Book hair & makeup trial',
                'Finalize the menu',
                'Follow up on pledges',
            ],
            '1-3 Months Before' => [
                'Send invitations',
                'Track RSVPs',
                'Write vows',
                'Create seating chart',
                'Confirm all vendors',
                'Send pledge reminders',
            ],
            'Final Weeks' => [
                'Final dress fitting',
                'Confirm final headcount with caterer',
                'Prepare payment envelopes for vendors',
                'Delegate day-of duties to wedding crew',
                'Send final reminders to guests',
                'Pack for the honeymoon',
            ],
            'The Big Day' => [
                'Give rings to the best man',
                'Enjoy every moment!',
            ],
        ];

        $phaseOrder = 0;
        foreach ($phases as $phaseName => $tasks) {
            $phase = $wedding->checklistPhases()->create([
                'name' => $phaseName,
                'sort_order' => $phaseOrder++,
            ]);

            $taskOrder = 0;
            foreach ($tasks as $task) {
                $phase->tasks()->create([
                    'title' => $task,
                    'sort_order' => $taskOrder++,
                ]);
            }
        }
    }
}
