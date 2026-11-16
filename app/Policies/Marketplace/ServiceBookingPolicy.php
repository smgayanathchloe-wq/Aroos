<?php

namespace App\Policies\Marketplace;

use App\Models\Marketplace\ServiceBooking;
use App\Models\User;

class ServiceBookingPolicy
{
    public function actAsWedding(User $user, ServiceBooking $booking): bool
    {
        return $user->canAccessWedding($booking->wedding);
    }

    public function actAsProvider(User $user, ServiceBooking $booking): bool
    {
        return ! $booking->provider->isBanned() && $booking->provider->allows($user, 'bookings');
    }
}
