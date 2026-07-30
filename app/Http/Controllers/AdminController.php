<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::count(),
            'planners' => User::where('role', User::ROLE_PLANNER)->count(),
            'couples' => User::where('role', User::ROLE_COUPLE)->count(),
            'weddings' => Wedding::count(),
        ];

        $users = User::withCount('weddings')->latest()->get();
        $weddings = Wedding::with('user')->latest()->get();

        return view('admin.index', compact('stats', 'users', 'weddings'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:super_admin,planner,couple'],
        ]);

        $data['password'] = Hash::make($data['password']);
        User::create($data);

        return back()->with('success', 'User created.');
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', 'in:super_admin,planner,couple']]);

        if ($user->id === $request->user()->id && $data['role'] !== User::ROLE_SUPER_ADMIN) {
            return back()->withErrors(['role' => 'You cannot demote your own account.']);
        }

        $user->update($data);

        return back()->with('success', 'Role updated for '.$user->email.'.');
    }

    public function destroyUser(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('success', 'User deleted.');
    }
}
