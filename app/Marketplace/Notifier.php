<?php

namespace App\Marketplace;

use App\Models\Marketplace\ProviderProfile;
use App\Models\User;
use App\Models\Wedding;
use App\Notifications\MarketplaceNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Who hears about what. Provider side: the owner and staff allowed to handle
 * enquiries. Wedding side: the wedding owner and its active collaborators.
 * Each (user, key) pair is notified once, so retries never duplicate.
 */
class Notifier
{
    public function provider(ProviderProfile $provider, string $event, string $title, string $body, string $url, string $key, ?User $except = null): void
    {
        $users = $provider->members()->with('user')->where('status', 'active')->get()
            ->filter(fn ($m) => $m->role === 'owner' || in_array('enquiries', $m->permissions ?? [], true))
            ->map->user;
        $this->send($users, $event, $title, $body, $url, $key, $except);
    }

    public function wedding(Wedding $wedding, string $event, string $title, string $body, string $url, string $key, ?User $except = null): void
    {
        $ids = $wedding->collaborators()->where('status', 'active')->pluck('user_id')->push($wedding->user_id);
        $this->send(User::whereIn('id', $ids)->get(), $event, $title, $body, $url, $key, $except);
    }

    public function user(User $user, string $event, string $title, string $body, string $url, string $key): void
    {
        $this->send(collect([$user]), $event, $title, $body, $url, $key);
    }

    private function send(Collection $users, string $event, string $title, string $body, string $url, string $key, ?User $except = null): void
    {
        foreach ($users->filter()->unique('id') as $user) {
            if ($except && $user->id === $except->id) {
                continue; // don't notify people about their own action
            }
            $already = DatabaseNotification::where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)
                ->where('data->key', $key)->exists();
            if ($already) {
                continue;
            }
            try {
                $user->notify(new MarketplaceNotification($event, $title, $body, $url, $key));
            } catch (\Throwable $e) {
                Log::warning('Marketplace notification failed', ['user' => $user->id, 'event' => $event, 'error' => $e->getMessage()]);
            }
        }
    }
}
