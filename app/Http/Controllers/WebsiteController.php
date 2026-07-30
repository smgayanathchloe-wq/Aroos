<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function edit(Wedding $wedding)
    {
        $settings = $wedding->websiteSetting ?: $wedding->websiteSetting()->create([
            'theme' => 'atelier',
            'sections' => ['hero', 'story', 'details', 'timeline', 'rsvp'],
        ]);

        return view('website.edit', compact('wedding', 'settings'));
    }

    public function update(Request $request, Wedding $wedding)
    {
        $settings = $wedding->websiteSetting;

        $data = $request->validate([
            'theme' => ['required', 'in:atelier,blush,royal,garden'],
            'published' => ['nullable', 'boolean'],
            'welcome_message' => ['nullable', 'string'],
            'sections' => ['nullable', 'array'],
            'hero_image' => ['nullable', 'image', 'max:15360'],
            'story' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('hero_image')) {
            $data['hero_image'] = $request->file('hero_image')->store('website/'.$wedding->id, 'public');
        } else {
            unset($data['hero_image']);
        }

        if (array_key_exists('story', $data)) {
            $wedding->update(['story' => $data['story']]);
            unset($data['story']);
        }

        $data['published'] = $request->boolean('published');
        $settings->update($data);

        return back()->with('success', 'Website updated.');
    }
}
