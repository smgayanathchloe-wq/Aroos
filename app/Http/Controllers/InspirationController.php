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
        try {
            $data = $request->validate([
                'title' => ['nullable', 'string', 'max:255'],
                'url' => ['nullable', 'url', 'max:2000'],
                'notes' => ['nullable', 'string'],
                'file' => ['nullable', 'image', 'max:15360'],
            ]);

            $type = 'note';
            $filePath = null;

            if ($request->hasFile('file')) {
                $storagePath = 'inspiration/'.$wedding->id;
                // Ensure directory exists
                \Storage::disk('public')->makeDirectory($storagePath);
                
                $filePath = $request->file('file')->store($storagePath, 'public');
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
        } catch (\Exception $e) {
            \Log::error('Inspiration upload failed: '.$e->getMessage(), ['wedding_id' => $wedding->id]);
            return back()->withErrors(['file' => 'Upload failed: '.$e->getMessage()]);
        }
    }

    public function destroy(Wedding $wedding, Inspiration $inspiration)
    {
        abort_unless($inspiration->wedding_id === $wedding->id, 404);
        $inspiration->delete();

        return back()->with('success', 'Removed.');
    }
}
