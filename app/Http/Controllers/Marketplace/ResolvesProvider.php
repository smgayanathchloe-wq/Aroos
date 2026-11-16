<?php

namespace App\Http\Controllers\Marketplace;

use App\Models\Marketplace\ProviderProfile;
use Illuminate\Http\Request;

trait ResolvesProvider
{
    /** The signed-in user's business, checked for the given staff permission. */
    protected function provider(Request $request, string $permission = 'services'): ProviderProfile
    {
        $provider = $request->user()->providerProfile();
        abort_unless($provider, 404, 'You have not registered a business yet.');
        abort_unless($provider->allows($request->user(), $permission), 403);
        if ($provider->isBanned() && ! $request->isMethodSafe()) {
            abort(403, 'This business has been suspended by Aroos.');
        }

        return $provider;
    }
}
