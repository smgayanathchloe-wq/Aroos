<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
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
