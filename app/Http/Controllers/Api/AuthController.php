<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    /** Failed sign-in attempts allowed before a lockout. */
    protected const MAX_ATTEMPTS = 5;

    protected const DECAY_SECONDS = 60;

    /**
     * Exchange credentials for a Sanctum token.
     *
     * Throttled per email+IP so a stolen address cannot be brute forced, and
     * the response never reveals whether an account exists.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => ['Too many attempts. Try again in '
                    .RateLimiter::availableIn($key).' seconds.'],
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            // Deliberately vague: do not confirm which part was wrong.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        RateLimiter::clear($key);

        $device = $data['device_name'] ?? 'mobile';

        // One live token per device keeps the table tidy and makes a
        // "sign out everywhere" meaningful.
        $user->tokens()->where('name', $device)->delete();
        $token = $user->createToken($device)->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user' => $this->profile($user),
            'weddings' => $this->weddingList($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return $this->ok([
            'user' => $this->profile($user),
            'weddings' => $this->weddingList($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(['message' => 'Signed out.']);
    }

    /** Revoke every token, for a lost or stolen device. */
    public function logoutAll(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->count();
        $request->user()->tokens()->delete();

        return $this->ok(['message' => 'Signed out of all devices.', 'revoked' => $count]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        $user->fill($data)->save();

        return $this->ok($this->profile($user->fresh()));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->user($request);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        // Changing a password invalidates every other session.
        $current = $request->user()->currentAccessToken();
        $user->tokens()->where('id', '!=', $current->id)->delete();

        return $this->ok(['message' => 'Password updated. Other devices were signed out.']);
    }

    protected function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_super_admin' => $user->isSuperAdmin(),
            'is_planner' => $user->isPlanner(),
            'is_couple' => $user->isCouple(),
            // Couples are view-only everywhere, which the UI uses to hide
            // editing affordances rather than letting them fail on submit.
            'can_edit' => ! $user->isCouple(),
            'can_create_weddings' => $user->canCreateWeddings(),
        ];
    }

    /**
     * Every wedding this user may open, with their rights on each.
     */
    protected function weddingList(User $user): array
    {
        return $user->accessibleWeddings()->map(fn (Wedding $wedding) => [
            'id' => $wedding->id,
            'slug' => $wedding->slug,
            'partner_a' => $wedding->partner_a,
            'partner_b' => $wedding->partner_b,
            'couple' => trim($wedding->partner_a.' & '.$wedding->partner_b),
            'wedding_date' => optional($wedding->wedding_date)->toDateString(),
            'venue_name' => $wedding->venue_name,
            'venue_city' => $wedding->venue_city,
            'currency' => $wedding->currency,
            'cover_photo' => $wedding->cover_photo ? url('storage/'.$wedding->cover_photo) : null,
            'is_owner' => $wedding->user_id === $user->id,
            'can_edit' => $user->canEditWedding($wedding),
        ])->values()->all();
    }
}
