<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Marketplace\BookingService;
use App\Marketplace\MarketplaceException;
use App\Marketplace\NegotiationService;
use App\Models\Marketplace\ServiceBooking;
use App\Models\Marketplace\ServiceEnquiry;
use App\Models\Wedding;
use Illuminate\Http\Request;

/**
 * The wedding's side of the marketplace. Everyone with access to the wedding,
 * couples included, can act here, so these routes sit outside the read-only
 * wedding.access middleware and authorize with canAccessWedding instead.
 */
class WeddingServicesController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Wedding $wedding)
    {
        $this->access($request, $wedding);
        $enquiries = $wedding->hasMany(ServiceEnquiry::class)->with(['provider', 'service', 'versions'])->latest('last_activity_at')->get();

        return view('marketplace.wedding.index', [
            'wedding' => $wedding,
            'open' => $enquiries->filter->isOpen(),
            'closed' => $enquiries->reject->isOpen()->where('status', '!=', 'accepted'),
            'bookings' => $wedding->hasMany(ServiceBooking::class)->with(['provider', 'service', 'package', 'review'])->orderBy('event_date')->get(),
            'favourites' => $request->user()->favouriteProviders()->with('primaryCategory')->get(),
        ]);
    }

    public function enquiry(Request $request, Wedding $wedding, ServiceEnquiry $enquiry)
    {
        abort_unless($enquiry->wedding_id === $wedding->id, 404);
        $this->authorize('viewAsWedding', $enquiry);
        $enquiry->load(['provider', 'service', 'package', 'versions.author', 'versions.package', 'messages.user', 'booking']);

        return view('marketplace.wedding.enquiry', ['wedding' => $wedding, 'enquiry' => $enquiry]);
    }

    public function compare(Request $request, Wedding $wedding)
    {
        $this->access($request, $wedding);
        $ids = array_slice(array_map('intval', (array) $request->get('ids', [])), 0, 4);
        $enquiries = ServiceEnquiry::with(['provider.region', 'service', 'package', 'versions.package'])
            ->where('wedding_id', $wedding->id)->whereIn('id', $ids)->get();

        return view('marketplace.wedding.compare', ['wedding' => $wedding, 'enquiries' => $enquiries]);
    }

    public function counter(Request $request, Wedding $wedding, ServiceEnquiry $enquiry, NegotiationService $negotiation)
    {
        $this->guard($request, $wedding, $enquiry);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        return $this->attempt(fn () => $negotiation->counter($enquiry, $request->user(), $data), 'Counter-offer sent.');
    }

    public function accept(Request $request, Wedding $wedding, ServiceEnquiry $enquiry, BookingService $bookings)
    {
        $this->guard($request, $wedding, $enquiry);
        $data = $request->validate(['version_id' => ['required', 'integer'], 'confirm' => ['accepted']]);

        return $this->attempt(fn () => $bookings->accept($enquiry, (int) $data['version_id'], $request->user()), 'Booking confirmed! It has been added to your vendors and budget.');
    }

    public function message(Request $request, Wedding $wedding, ServiceEnquiry $enquiry, NegotiationService $negotiation)
    {
        $this->guard($request, $wedding, $enquiry);
        $body = $request->validate(['body' => ['required', 'string', 'max:3000']])['body'];

        return $this->attempt(fn () => $negotiation->message($enquiry, $request->user(), 'customer', $body), 'Message sent.');
    }

    public function withdraw(Request $request, Wedding $wedding, ServiceEnquiry $enquiry, NegotiationService $negotiation)
    {
        $this->guard($request, $wedding, $enquiry);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        return $this->attempt(fn () => $negotiation->decline($enquiry, $request->user(), 'customer', $reason), 'Request withdrawn.');
    }

    public function cancelBooking(Request $request, Wedding $wedding, ServiceBooking $booking, BookingService $bookings)
    {
        abort_unless($booking->wedding_id === $wedding->id, 404);
        $this->authorize('actAsWedding', $booking);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        return $this->attempt(fn () => $bookings->cancel($booking, $request->user(), 'customer', $reason), 'Booking cancelled. The provider has been notified.');
    }

    private function access(Request $request, Wedding $wedding): void
    {
        abort_unless($request->user()->canAccessWedding($wedding), 403);
    }

    private function guard(Request $request, Wedding $wedding, ServiceEnquiry $enquiry): void
    {
        abort_unless($enquiry->wedding_id === $wedding->id, 404);
        $this->authorize('actAsWedding', $enquiry);
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
