<?php

namespace App\Marketplace;

use App\Models\Marketplace\ProviderBlackout;
use App\Models\Marketplace\ProviderService;
use App\Models\Marketplace\ServiceBooking;
use Carbon\CarbonImmutable;

/**
 * Capacity rules per service:
 *  - per_day:  at most capacity_per_day confirmed bookings on a date (a venue: 1; a caterer: several)
 *  - per_slot: at most capacity_per_day bookings overlapping the requested time window, with buffer
 *  - capacity_scope=provider counts bookings across all the provider's services (a one-person MC)
 * Blackout dates block the provider (or one service). Server-side only; the UI just displays results.
 */
class AvailabilityService
{
    /** @return string|null reason the slot is unavailable, null if it is free */
    public function conflict(ProviderService $service, string $date, ?string $start = null, ?string $end = null, ?int $ignoreBookingId = null): ?string
    {
        $blackout = ProviderBlackout::where('provider_id', $service->provider_id)
            ->where(fn ($q) => $q->whereNull('service_id')->orWhere('service_id', $service->id))
            ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->exists();
        if ($blackout) {
            return 'The provider is not available on this date.';
        }

        $bookings = ServiceBooking::where('status', 'confirmed')->whereDate('event_date', $date)
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->when($service->capacity_scope === 'provider',
                fn ($q) => $q->where('provider_id', $service->provider_id),
                fn ($q) => $q->where('service_id', $service->id))
            ->get(['starts_at', 'ends_at']);

        $capacity = max(1, (int) $service->capacity_per_day);

        if ($service->scheduling === 'per_slot' && $start) {
            $buffer = (int) $service->buffer_minutes;
            $from = CarbonImmutable::parse("$date $start")->subMinutes($buffer);
            $to = CarbonImmutable::parse("$date ".($end ?: $start))->addMinutes($buffer + ($end ? 0 : max(60, (int) $service->duration_minutes)));
            $overlapping = $bookings->filter(function ($b) use ($date, $from, $to) {
                if (! $b->starts_at) {
                    return true; // a booking without times blocks the whole day
                }
                $bStart = CarbonImmutable::parse("$date {$b->starts_at}");
                $bEnd = CarbonImmutable::parse("$date ".($b->ends_at ?: $b->starts_at))->addMinutes($b->ends_at ? 0 : 60);

                return $bStart->lt($to) && $bEnd->gt($from);
            });

            return $overlapping->count() >= $capacity ? 'This time slot is already booked.' : null;
        }

        return $bookings->count() >= $capacity ? 'The provider is fully booked on this date.' : null;
    }

    /** Booked and blocked days of a month for the provider calendar. */
    public function month(int $providerId, CarbonImmutable $month): array
    {
        return $this->range($providerId, $month->startOfMonth(), $month->endOfMonth());
    }

    /** Confirmed/completed bookings, tentative requests and blocks between two dates, grouped by day. */
    public function range(int $providerId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'bookings' => ServiceBooking::with(['service', 'wedding'])->where('provider_id', $providerId)
                ->whereDate('event_date', '>=', $from->toDateString())->whereDate('event_date', '<=', $to->toDateString())
                ->whereIn('status', ServiceBooking::ACTIVE)->orderBy('event_date')->orderBy('starts_at')->get()->groupBy(fn ($b) => $b->event_date->toDateString()),
            'tentative' => \App\Models\Marketplace\ServiceEnquiry::where('provider_id', $providerId)
                ->whereIn('status', ['quoted', 'negotiating'])
                ->whereDate('event_date', '>=', $from->toDateString())->whereDate('event_date', '<=', $to->toDateString())
                ->get()->groupBy(fn ($e) => $e->event_date->toDateString()),
            'blackouts' => ProviderBlackout::with('service')->where('provider_id', $providerId)
                ->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)->get(),
        ];
    }
}
