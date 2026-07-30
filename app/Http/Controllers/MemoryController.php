<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Models\Wedding;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function index(Wedding $wedding)
    {
        $memories = $wedding->memories()->latest()->get();

        return view('memories.index', compact('wedding', 'memories'));
    }

    public function destroy(Wedding $wedding, Memory $memory)
    {
        abort_unless($memory->wedding_id === $wedding->id, 404);
        $memory->delete();

        return back()->with('success', 'Memory removed.');
    }
}
