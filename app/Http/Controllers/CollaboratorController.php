<?php

namespace App\Http\Controllers;

use App\Models\Collaborator;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CollaboratorController extends Controller
{
    public function index(Wedding $wedding)
    {
        $collaborators = $wedding->collaborators()->with('user')->get();

        return view('collaborators.index', compact('wedding', 'collaborators'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:partner,planner,family,wedding_party,vendor'],
        ]);

        if ($wedding->collaborators()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'Already invited.']);
        }

        $user = User::where('email', $data['email'])->first();

        $wedding->collaborators()->create([
            'email' => $data['email'],
            'role' => $data['role'],
            'user_id' => $user?->id,
            'status' => $user ? 'active' : 'pending',
            'token' => Str::random(40),
        ]);

        return back()->with('success', $user ? 'Collaborator added.' : 'Invitation created. They can join once they register with this email.');
    }

    public function destroy(Wedding $wedding, Collaborator $collaborator)
    {
        abort_unless($collaborator->wedding_id === $wedding->id, 404);
        $collaborator->delete();

        return back()->with('success', 'Collaborator removed.');
    }
}
