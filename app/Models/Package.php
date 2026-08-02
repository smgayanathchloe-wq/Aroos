<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $guarded = [];

    protected $casts = [
        'features' => 'array',
        'active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(WeddingSubscription::class);
    }

    /**
     * Get features array with defaults for this package
     */
    public function getFeatures(): array
    {
        return $this->features ?? [
            'max_guests' => $this->max_guests,
            'qr_checkin' => true,
            'sms_reminders' => $this->name !== 'Harusi',
            'email_campaigns' => $this->name !== 'Harusi',
            'pledge_tracking' => true,
            'website_designs' => $this->name === 'Studio' ? 4 : 1,
            'album_categories' => $this->name !== 'Harusi',
            'team_collaboration' => $this->name === 'Studio',
            'priority_support' => $this->name === 'Studio',
            'custom_branding' => false,
            'api_access' => false,
        ];
    }

    /**
     * Check if feature is enabled for this package
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->getFeatures();
        return $features[$feature] ?? false;
    }
}
