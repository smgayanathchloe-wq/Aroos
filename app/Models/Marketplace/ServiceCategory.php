<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort'); }
    public function providers(): BelongsToMany { return $this->belongsToMany(ProviderProfile::class, 'provider_category', 'category_id', 'provider_id'); }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }
    public function scopeTopLevel(Builder $q): Builder { return $q->whereNull('parent_id'); }

    public function label(): string
    {
        return app()->getLocale() === 'sw' && $this->name_sw ? $this->name_sw : $this->name;
    }
}
