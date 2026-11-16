<?php

namespace App\Policies\Marketplace;

use App\Models\Marketplace\ProviderProfile;
use App\Models\User;

class ProviderProfilePolicy
{
    /** Listed providers are visible to signed-in users; members and admins also see unlisted ones. */
    public function view(User $user, ProviderProfile $provider): bool
    {
        return $provider->isListed() || $user->isSuperAdmin() || $provider->memberFor($user) !== null;
    }

    public function manage(User $user, ProviderProfile $provider, string $permission = 'services'): bool
    {
        return ! $provider->isBanned() && $provider->allows($user, $permission);
    }

    public function own(User $user, ProviderProfile $provider): bool
    {
        return $provider->memberFor($user)?->role === 'owner';
    }
}
