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
            'design' => 'royal-gold',
            'sections' => ['hero', 'story', 'details', 'timeline', 'rsvp'],
        ]);

        $albums = $wedding->albums()->withCount('photos')->get();

        return view('website.edit', compact('wedding', 'settings', 'albums'));
    }

    public function update(Request $request, Wedding $wedding)
    {
        $settings = $wedding->websiteSetting;

        $data = $request->validate([
            'design' => ['required', 'in:royal-gold,blush-rose,emerald-lux,midnight-pearl'],
            'mode' => ['required', 'in:before,after'],
            'font_style' => ['required', 'in:serif,script,modern'],
            'published' => ['nullable', 'boolean'],
            'welcome_message' => ['nullable', 'string'],
            'after_message' => ['nullable', 'string'],
            'sections' => ['nullable', 'array'],
            'banner_image' => ['nullable', 'image', 'max:15360'],
            'bride_name' => ['nullable', 'string', 'max:100'],
            'bride_bio' => ['nullable', 'string'],
            'bride_photo' => ['nullable', 'image', 'max:15360'],
            'groom_name' => ['nullable', 'string', 'max:100'],
            'groom_bio' => ['nullable', 'string'],
            'groom_photo' => ['nullable', 'image', 'max:15360'],
            'accent_override' => ['nullable', 'string', 'max:20'],
            'story' => ['nullable', 'string'],
            'slide_images' => ['nullable', 'array'],
            'slide_images.*' => ['image', 'max:15360'],
            'slide_captions' => ['nullable', 'array'],
            'slide_captions.*' => ['nullable', 'string', 'max:255'],
            'remove_slides' => ['nullable', 'array'],
        ]);

        foreach (['banner_image', 'bride_photo', 'groom_photo'] as $img) {
            if ($request->hasFile($img)) {
                $data[$img] = $request->file($img)->store('website/'.$wedding->id, 'public');
            } else {
                unset($data[$img]);
            }
        }

        if (array_key_exists('story', $data)) {
            $wedding->update(['story' => $data['story']]);
            unset($data['story']);
        }

        // Slides management
        $slides = collect($settings->slides ?? []);
        if (! empty($data['remove_slides'])) {
            $slides = $slides->reject(fn ($s, $i) => in_array($i, $data['remove_slides']));
        }
        if ($request->hasFile('slide_images')) {
            foreach ($request->file('slide_images') as $i => $file) {
                $slides->push([
                    'image' => $file->store('website/'.$wedding->id.'/slides', 'public'),
                    'caption' => $data['slide_captions'][$i] ?? '',
                ]);
            }
        }
        $data['slides'] = $slides->values()->all();
        unset($data['slide_images'], $data['slide_captions'], $data['remove_slides']);

        $data['published'] = $request->boolean('published');
        $settings->update($data);

        return back()->with('success', 'Website updated. Open the live site to see it.');
    }
}
