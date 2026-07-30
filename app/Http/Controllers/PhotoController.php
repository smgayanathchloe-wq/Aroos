<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Wedding;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function index(Wedding $wedding)
    {
        $photos = $wedding->photos()->latest()->get();

        return view('photos.index', compact('wedding', 'photos'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $request->validate([
            'photos' => ['required', 'array'],
            'photos.*' => ['image', 'max:15360'],
        ]);

        foreach ($request->file('photos') as $file) {
            $path = $file->store('photos/'.$wedding->id, 'public');
            $wedding->photos()->create([
                'file_path' => $path,
                'uploaded_by' => $request->user()->name,
                'source' => 'owner',
            ]);
        }

        return back()->with('success', 'Photos uploaded.');
    }

    public function destroy(Wedding $wedding, Photo $photo)
    {
        abort_unless($photo->wedding_id === $wedding->id, 404);
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }
}
