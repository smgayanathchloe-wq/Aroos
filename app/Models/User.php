<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_PLANNER = 'planner';
    public const ROLE_COUPLE = 'couple';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_admin',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function weddings(): HasMany
    {
        return $this->hasMany(Wedding::class);
    }

    public function providerMemberships(): HasMany
    {
        return $this->hasMany(\App\Models\Marketplace\ProviderMember::class)->where('status', 'active');
    }

    /** The service provider business this user owns or works for, if any. */
    public function providerProfile(): ?\App\Models\Marketplace\ProviderProfile
    {
        // Staff invited by email before they had an account join on first visit.
        if ($this->email) {
            \App\Models\Marketplace\ProviderMember::whereNull('user_id')->where('status', 'invited')
                ->where('email', strtolower($this->email))
                ->update(['user_id' => $this->id, 'status' => 'active', 'updated_at' => now()]);
        }

        $membership = $this->providerMemberships()->with('provider.members')->orderByRaw("role = 'owner' desc")->first();

        return $membership?->provider;
    }

    public function favouriteProviders(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Marketplace\ProviderProfile::class, 'favourite_providers', 'user_id', 'provider_id')
            ->withPivot('created_at');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isPlanner(): bool
    {
        return $this->role === self::ROLE_PLANNER;
    }

    public function isCouple(): bool
    {
        return $this->role === self::ROLE_COUPLE;
    }

    public function accessibleWeddings()
    {
        if ($this->isSuperAdmin()) {
            return Wedding::latest()->get();
        }

        $collabIds = Collaborator::where('user_id', $this->id)->where('status', 'active')->pluck('wedding_id');

        return Wedding::where('user_id', $this->id)->orWhereIn('id', $collabIds)->get();
    }

    public function canAccessWedding(Wedding $wedding): bool
    {
        if ($this->isSuperAdmin() || $wedding->user_id === $this->id) {
            return true;
        }

        return $wedding->collaborators()->where('user_id', $this->id)->where('status', 'active')->exists();
    }

    /**
     * Couples get read-only access; planners/super admins can modify
     * any wedding they can access.
     */
    public function canEditWedding(Wedding $wedding): bool
    {
        return $this->canAccessWedding($wedding) && ! $this->isCouple();
    }

    public function canCreateWeddings(): bool
    {
        return ! $this->isCouple();
    }
}
