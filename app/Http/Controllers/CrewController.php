<?php

namespace App\Http\Controllers;

use App\Models\CrewMember;
use App\Models\CrewTeam;
use App\Models\Wedding;
use Illuminate\Http\Request;

class CrewController extends Controller
{
    public function index(Wedding $wedding)
    {
        $teams = $wedding->crewTeams()->with('members')->get();

        return view('crew.index', compact('wedding', 'teams'));
    }

    public function storeTeam(Request $request, Wedding $wedding)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $wedding->crewTeams()->create([...$data, 'responsibilities' => []]);

        return back()->with('success', 'Team added.');
    }

    public function updateTeam(Request $request, Wedding $wedding, CrewTeam $team)
    {
        abort_unless($team->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'responsibility' => ['nullable', 'string', 'max:255'],
            'remove_responsibility' => ['nullable', 'integer'],
        ]);

        if (isset($data['name'])) {
            $team->name = $data['name'];
        }

        $resp = $team->responsibilities ?? [];
        if (! empty($data['responsibility'])) {
            $resp[] = $data['responsibility'];
        }
        if (isset($data['remove_responsibility'])) {
            unset($resp[$data['remove_responsibility']]);
            $resp = array_values($resp);
        }
        $team->responsibilities = $resp;
        $team->save();

        return back()->with('success', 'Team updated.');
    }

    public function destroyTeam(Wedding $wedding, CrewTeam $team)
    {
        abort_unless($team->wedding_id === $wedding->id, 404);
        $team->delete();

        return back()->with('success', 'Team removed.');
    }

    public function storeMember(Request $request, Wedding $wedding, CrewTeam $team)
    {
        abort_unless($team->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['nullable', 'string', 'max:100'],
        ]);
        $team->members()->create($data);

        return back()->with('success', 'Member added.');
    }

    public function destroyMember(Wedding $wedding, CrewTeam $team, CrewMember $member)
    {
        abort_unless($team->wedding_id === $wedding->id && $member->crew_team_id === $team->id, 404);
        $member->delete();

        return back()->with('success', 'Member removed.');
    }
}
