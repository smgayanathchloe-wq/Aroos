<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProviderProfile extends Model
{
    use SoftDeletes;

    public const PERMISSIONS = ['enquiries', 'respond', 'calendar', 'bookings', 'services', 'reports'];

    protected $guarded = [];

    protected $casts = [
        'operating_hours' => 'array',
        'social_links' => 'array',
        'verified_at' => 'datetime',
        'featured_until' => 'datetime',
        'is_sponsored' => 'boolean',
        'has_packages' => 'boolean',
        'has_portfolio' => 'boolean',
        'starting_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            if (! $p->slug) {
                $base = Str::slug($p->business_name) ?: 'provider';
                $slug = $base;
                $i = 2;
                while (static::withTrashed()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $p->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function primaryCategory(): BelongsTo { return $this->belongsTo(ServiceCategory::class, 'primary_category_id'); }
    public function region(): BelongsTo { return $this->belongsTo(TzRegion::class); }
    public function categories(): BelongsToMany { return $this->belongsToMany(ServiceCategory::class, 'provider_category', 'provider_id', 'category_id'); }
    public function members(): HasMany { return $this->hasMany(ProviderMember::class, 'provider_id'); }
    public function services(): HasMany { return $this->hasMany(ProviderService::class, 'provider_id')->orderBy('sort'); }
    public function media(): HasMany { return $this->hasMany(ProviderMedia::class, 'provider_id')->orderBy('sort')->orderBy('id'); }
    public function blackouts(): HasMany { return $this->hasMany(ProviderBlackout::class, 'provider_id'); }
    public function verifications(): HasMany { return $this->hasMany(ProviderVerification::class, 'provider_id')->latest('id'); }
    public function enquiries(): HasMany { return $this->hasMany(ServiceEnquiry::class, 'provider_id'); }
    public function bookings(): HasMany { return $this->hasMany(ServiceBooking::class, 'provider_id'); }

    /** Visible in the marketplace: published, verified and not banned. */
    public function scopeListed(Builder $q): Builder
    {
        return $q->where('status', 'active')->where('verification_status', 'verified');
    }

    public function isListed(): bool
    {
        return $this->status === 'active' && $this->verification_status === 'verified';
    }

    public function isBanned(): bool { return $this->status === 'banned'; }

    public function memberFor(?User $user): ?ProviderMember
    {
        return $user ? $this->members->first(fn (ProviderMember $m) => $m->user_id === $user->id && $m->status === 'active') : null;
    }

    /** Owner can do everything; staff only what the owner granted. */
    public function allows(?User $user, string $permission): bool
    {
        $member = $this->memberFor($user);

        return $member && ($member->role === 'owner' || in_array($permission, $member->permissions ?? [], true));
    }

    public function logoUrl(): ?string { return $this->logo_path ? asset('storage/'.$this->logo_path) : null; }
    public function coverUrl(): ?string { return $this->cover_path ? asset('storage/'.$this->cover_path) : null; }
}
