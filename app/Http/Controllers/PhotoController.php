<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Wedding;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function index(Wedding $wedding)
    {
        $albums = $wedding->albums()->with('photos')->get();
        $unfiled = $wedding->photos()->whereNull('album_id')->latest()->get();

        return view('photos.index', compact('wedding', 'albums', 'unfiled'));
    }

    public function storeAlbum(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'style' => ['required', 'in:grid,masonry,carousel'],
            'accent_color' => ['nullable', 'string', 'max:20'],
        ]);
        $data['sort_order'] = ($wedding->albums()->max('sort_order') ?? 0) + 1;
        $wedding->albums()->create($data);

        return back()->with('success', 'Album created.');
    }

    public function updateAlbum(Request $request, Wedding $wedding, Album $album)
    {
        abort_unless($album->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'style' => ['required', 'in:grid,masonry,carousel'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'public' => ['nullable', 'boolean'],
        ]);
        $data['public'] = $request->boolean('public');
        $album->update($data);

        return back()->with('success', 'Album updated.');
    }

    public function destroyAlbum(Wedding $wedding, Album $album)
    {
        abort_unless($album->wedding_id === $wedding->id, 404);
        $album->delete();

        return back()->with('success', 'Album removed (photos moved to unfiled).');
    }

    public function store(Request $request, Wedding $wedding)
    {
        $request->validate([
            'photos' => ['required', 'array'],
            'photos.*' => ['image', 'max:15360'],
            'album_id' => ['nullable', 'integer'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $albumId = $request->input('album_id');
        if ($albumId) {
            $album = Album::findOrFail($albumId);
            abort_unless($album->wedding_id === $wedding->id, 404);
        }

        foreach ($request->file('photos') as $file) {
            $path = $file->store('photos/'.$wedding->id, 'public');
            $wedding->photos()->create([
                'file_path' => $path,
                'album_id' => $albumId ?: null,
                'caption' => $request->input('caption'),
                'uploaded_by' => $request->user()->name,
                'source' => 'owner',
            ]);
        }

        return back()->with('success', 'Photos uploaded.');
    }

    public function move(Request $request, Wedding $wedding, Photo $photo)
    {
        abort_unless($photo->wedding_id === $wedding->id, 404);
        $data = $request->validate(['album_id' => ['nullable', 'integer']]);

        if ($data['album_id']) {
            $album = Album::findOrFail($data['album_id']);
            abort_unless($album->wedding_id === $wedding->id, 404);
        }

        $photo->update(['album_id' => $data['album_id'] ?: null]);

        return back()->with('success', 'Photo moved.');
    }

    public function destroy(Wedding $wedding, Photo $photo)
    {
        abort_unless($photo->wedding_id === $wedding->id, 404);
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }
}
