<?php

namespace App\Http\Controllers;

use App\Models\Inspiration;
use App\Models\Wedding;
use Illuminate\Http\Request;

class InspirationController extends Controller
{
    public function index(Wedding $wedding)
    {
        $inspirations = $wedding->inspirations()->latest()->get();

        return view('inspiration.index', compact('wedding', 'inspirations'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2000'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'image', 'max:15360'],
        ]);

        $type = 'note';
        $filePath = null;

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('inspiration/'.$wedding->id, 'public');
            $type = 'image';
        } elseif (! empty($data['url'])) {
            $type = preg_match('/youtube|vimeo|\\.mp4/i', $data['url']) ? 'video' : 'image';
        }

        $wedding->inspirations()->create([
            'title' => $data['title'] ?? null,
            'type' => $type,
            'url' => $data['url'] ?? null,
            'file_path' => $filePath,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Inspiration added.');
    }

    public function destroy(Wedding $wedding, Inspiration $inspiration)
    {
        abort_unless($inspiration->wedding_id === $wedding->id, 404);
        $inspiration->delete();

        return back()->with('success', 'Removed.');
    }
}
