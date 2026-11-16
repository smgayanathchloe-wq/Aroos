<?php

namespace App\Console\Commands;

use App\Marketplace\BookingService;
use App\Marketplace\NegotiationService;
use App\Models\Marketplace\ServiceBooking;
use Illuminate\Console\Command;

class MarketplaceHousekeeping extends Command
{
    protected $signature = 'marketplace:housekeeping';

    protected $description = 'Expire stale enquiries and offers; complete bookings whose event has passed';

    public function handle(NegotiationService $negotiation, BookingService $bookings): int
    {
        $expired = $negotiation->expire();

        $completed = 0;
        ServiceBooking::where('status', 'confirmed')->whereDate('event_date', '<', now()->toDateString())->each(function ($booking) use ($bookings, &$completed) {
            $bookings->complete($booking);
            $completed++;
        });

        $this->info("Expired {$expired['enquiries']} request(s) and {$expired['versions']} offer(s); completed {$completed} booking(s).");

        return self::SUCCESS;
    }
}
