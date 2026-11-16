<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceException;
use App\Marketplace\ReviewService;
use App\Models\Marketplace\MarketplaceReport;
use App\Models\Marketplace\ServiceBooking;
use App\Models\Wedding;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Reviews from couples, reports from any signed-in user, notification preferences. */
class TrustController extends Controller
{
    public function review(Request $request, Wedding $wedding, ServiceBooking $booking, ReviewService $reviews)
    {
        abort_unless($booking->wedding_id === $wedding->id, 404);
        $rating = ['nullable', 'integer', 'between:1,5'];
        $data = $request->validate([
            'overall' => ['required', 'integer', 'between:1,5'],
            'quality' => $rating, 'communication' => $rating, 'professionalism' => $rating, 'value' => $rating,
            'body' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $reviews->create($booking, $request->user(), $data);
        } catch (MarketplaceException $e) {
            return back()->withErrors(['marketplace' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['marketplace' => 'This booking has already been reviewed.']);
        }

        return back()->with('success', 'Thank you! Your review is published.');
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(MarketplaceReport::TYPES))],
            'id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $class = MarketplaceReport::TYPES[$data['type']];
        abort_unless($class::query()->whereKey($data['id'])->exists(), 404);

        try {
            MarketplaceReport::create(['reporter_user_id' => $request->user()->id, 'reportable_type' => $data['type'], 'reportable_id' => $data['id'], 'reason' => $data['reason']]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('success', 'You have already reported this. Our team will review it.');
        }

        return back()->with('success', 'Thanks — the Aroos team will review your report.');
    }

    public function preferences(Request $request)
    {
        DB::table('notification_preferences')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['email' => $request->boolean('email'), 'sms' => $request->boolean('sms'), 'updated_at' => now(), 'created_at' => now()],
        );

        return back()->with('success', 'Notification preferences saved.');
    }
}
