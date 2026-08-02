<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Wedding extends Model
{
    protected $guarded = [];

    protected $casts = [
        'wedding_date' => 'date',
        'total_budget' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Wedding $wedding) {
            if (! $wedding->slug) {
                $base = Str::slug($wedding->partner_a.'-'.$wedding->partner_b);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $wedding->slug = $slug;
            }
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function collaborators(): HasMany { return $this->hasMany(Collaborator::class); }
    public function checklistPhases(): HasMany { return $this->hasMany(ChecklistPhase::class)->orderBy('sort_order'); }
    public function budgetCategories(): HasMany { return $this->hasMany(BudgetCategory::class); }
    public function guests(): HasMany { return $this->hasMany(Guest::class); }
    public function seatingTables(): HasMany { return $this->hasMany(SeatingTable::class); }
    public function vendors(): HasMany { return $this->hasMany(Vendor::class); }
    public function crewTeams(): HasMany { return $this->hasMany(CrewTeam::class); }
    public function timelineEvents(): HasMany { return $this->hasMany(TimelineEvent::class)->orderBy('start_time'); }
    public function menus(): HasMany { return $this->hasMany(Menu::class)->orderBy('sort_order'); }
    public function inspirations(): HasMany { return $this->hasMany(Inspiration::class); }
    public function photos(): HasMany { return $this->hasMany(Photo::class); }
    public function albums(): HasMany { return $this->hasMany(Album::class)->orderBy('sort_order'); }
    public function floorPlanElements(): HasMany { return $this->hasMany(FloorPlanElement::class); }
    public function memories(): HasMany { return $this->hasMany(Memory::class); }
    public function websiteSetting(): HasOne { return $this->hasOne(WebsiteSetting::class); }
    public function pledges(): HasMany { return $this->hasMany(Pledge::class); }
    public function contributions(): HasMany { return $this->hasMany(Contribution::class); }
    public function reminders(): HasMany { return $this->hasMany(Reminder::class); }
    public function storagePlan(): HasOne { return $this->hasOne(StoragePlan::class); }
    public function subscriptions(): HasMany { return $this->hasMany(WeddingSubscription::class); }

    public function coupleNames(): string
    {
        return $this->partner_a.' & '.$this->partner_b;
    }

    public function daysToGo(): ?int
    {
        return $this->wedding_date ? (int) now()->startOfDay()->diffInDays($this->wedding_date, false) : null;
    }

    public function isPast(): bool
    {
        return $this->wedding_date !== null && $this->wedding_date->isPast() && ! $this->wedding_date->isToday();
    }
}
