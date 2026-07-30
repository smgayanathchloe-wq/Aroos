<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Wedding;
use Illuminate\Http\Request;

class PublicSiteController extends Controller
{
    public function site(string $slug)
    {
        $wedding = Wedding::where('slug', $slug)->firstOrFail();
        $settings = $wedding->websiteSetting;

        abort_unless($settings && $settings->published, 404);

        $wedding->load(['timelineEvents' => fn ($q) => $q->where('public', true), 'menus.items']);
        $albums = $wedding->albums()->where('public', true)->with('photos')->get();

        return view('public.site', compact('wedding', 'settings', 'albums'));
    }

    public function rsvp(string $slug, string $token)
    {
        $wedding = Wedding::where('slug', $slug)->firstOrFail();
        $guest = Guest::where('wedding_id', $wedding->id)->where('invite_token', $token)->firstOrFail();

        return view('public.rsvp', compact('wedding', 'guest'));
    }

    public function submitRsvp(Request $request, string $slug, string $token)
    {
        $wedding = Wedding::where('slug', $slug)->firstOrFail();
        $guest = Guest::where('wedding_id', $wedding->id)->where('invite_token', $token)->firstOrFail();

        $data = $request->validate([
            'rsvp_status' => ['required', 'in:attending,declined,maybe'],
            'party_count' => ['required', 'integer', 'min:1', 'max:20'],
            'meal_preference' => ['nullable', 'string', 'max:100'],
            'dietary_notes' => ['nullable', 'string'],
        ]);

        $guest->update([...$data, 'rsvp_at' => now()]);

        return view('public.rsvp-thanks', compact('wedding', 'guest'));
    }

    public function invitation(string $slug, string $token)
    {
        $wedding = Wedding::where('slug', $slug)->firstOrFail();
        $guest = Guest::where('wedding_id', $wedding->id)->where('invite_token', $token)->firstOrFail();

        return view('public.invitation', compact('wedding', 'guest'));
    }
}
