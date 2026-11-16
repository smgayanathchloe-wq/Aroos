<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Marketplace\AvailabilityService;
use App\Marketplace\BookingService;
use App\Marketplace\MarketplaceException;
use App\Marketplace\NegotiationService;
use App\Models\Marketplace\ProviderBlackout;
use App\Models\Marketplace\ServiceBooking;
use App\Models\Marketplace\ServiceEnquiry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Requests, quotations, bookings and calendar on the provider side. */
class ProviderWorkController extends Controller
{
    use AuthorizesRequests, ResolvesProvider;

    public function enquiries(Request $request)
    {
        $provider = $this->provider($request, 'enquiries');
        $tab = $request->get('tab', 'open');

        return view('marketplace.provider.enquiries', [
            'provider' => $provider,
            'tab' => $tab,
            'enquiries' => $provider->enquiries()->with(['service', 'versions'])
                ->when($tab === 'open', fn ($q) => $q->whereIn('status', ServiceEnquiry::OPEN))
                ->when($tab === 'closed', fn ($q) => $q->whereNotIn('status', ServiceEnquiry::OPEN))
                ->latest('last_activity_at')->paginate(30)->withQueryString(),
        ]);
    }

    public function enquiry(Request $request, ServiceEnquiry $enquiry)
    {
        $this->authorize('viewAsProvider', $enquiry);
        $enquiry->load(['service.activePackages', 'package', 'versions.author', 'messages.user', 'booking', 'region']);

        return view('marketplace.provider.enquiry', [
            'provider' => $enquiry->provider,
            'enquiry' => $enquiry,
            'canRespond' => $request->user()->can('respondAsProvider', $enquiry),
            'conflict' => $enquiry->service && $enquiry->isOpen()
                ? app(AvailabilityService::class)->conflict($enquiry->service, $enquiry->event_date->toDateString(), $enquiry->event_start, $enquiry->event_end)
                : null,
        ]);
    }

    public function quote(Request $request, ServiceEnquiry $enquiry, NegotiationService $negotiation)
    {
        $this->authorize('respondAsProvider', $enquiry);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'package_id' => ['nullable', 'integer'],
            'proposed_date' => ['nullable', 'date', 'after_or_equal:today'],
            'terms' => ['nullable', 'string', 'max:3000'],
            'message' => ['nullable', 'string', 'max:3000'],
            'valid_days' => ['nullable', 'integer', 'between:1,60'],
        ]);

        return $this->attempt(fn () => $negotiation->quote($enquiry, $request->user(), $data), 'Quotation sent.');
    }

    public function message(Request $request, ServiceEnquiry $enquiry, NegotiationService $negotiation)
    {
        $this->authorize('respondAsProvider', $enquiry);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
            'kind' => ['required', 'in:message,info_request,meeting_request'],
        ]);

        return $this->attempt(fn () => $negotiation->message($enquiry, $request->user(), 'provider', $data['body'], $data['kind']), 'Message sent.');
    }

    public function decline(Request $request, ServiceEnquiry $enquiry, NegotiationService $negotiation)
    {
        $this->authorize('respondAsProvider', $enquiry);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        return $this->attempt(fn () => $negotiation->decline($enquiry, $request->user(), 'provider', $reason), 'Request declined.');
    }

    public function bookings(Request $request)
    {
        $provider = $this->provider($request, 'bookings');

        return view('marketplace.provider.bookings', [
            'provider' => $provider,
            'bookings' => $provider->bookings()->with(['service', 'package', 'enquiry'])
                ->orderByRaw("status = 'confirmed' desc")->orderBy('event_date')->paginate(30),
        ]);
    }

    public function completeBooking(Request $request, ServiceBooking $booking, BookingService $service)
    {
        $this->authorize('actAsProvider', $booking);

        return $this->attempt(fn () => $service->complete($booking, $request->user()), 'Booking marked as completed.');
    }

    public function cancelBooking(Request $request, ServiceBooking $booking, BookingService $service)
    {
        $this->authorize('actAsProvider', $booking);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        return $this->attempt(fn () => $service->cancel($booking, $request->user(), 'provider', $reason), 'Booking cancelled. The couple has been notified.');
    }

    public function calendar(Request $request, AvailabilityService $availability)
    {
        $provider = $this->provider($request, 'calendar');
        $view = in_array($request->get('view'), ['month', 'week', 'day'], true) ? $request->get('view') : 'month';
        try {
            $date = CarbonImmutable::parse($request->get('date') ?: ($request->get('month') ? $request->get('month').'-01' : 'today'))->startOfDay();
        } catch (\Throwable) {
            $date = CarbonImmutable::today();
        }

        [$from, $to] = match ($view) {
            'day' => [$date, $date],
            'week' => [$date->startOfWeek(CarbonImmutable::MONDAY), $date->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()],
            default => [$date->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY), $date->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()],
        };

        return view('marketplace.provider.calendar', [
            'provider' => $provider,
            'view' => $view,
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'data' => $availability->range($provider->id, $from, $to),
            'services' => $provider->services()->get(),
        ]);
    }

    public function storeBlackout(Request $request)
    {
        $provider = $this->provider($request, 'calendar');
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'service_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        if (! empty($data['service_id']) && ! $provider->services()->whereKey($data['service_id'])->exists()) {
            $data['service_id'] = null;
        }
        $provider->blackouts()->create($data);

        return back()->with('success', 'Dates blocked.');
    }

    public function destroyBlackout(Request $request, ProviderBlackout $blackout)
    {
        $provider = $this->provider($request, 'calendar');
        abort_unless($blackout->provider_id === $provider->id, 404);
        $blackout->delete();

        return back()->with('success', 'Dates unblocked.');
    }

    private function attempt(callable $action, string $success)
    {
        try {
            $action();
        } catch (MarketplaceException $e) {
            return back()->withInput()->withErrors(['marketplace' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
