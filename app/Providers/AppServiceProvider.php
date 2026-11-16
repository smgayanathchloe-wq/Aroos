<?php

namespace App\Providers;

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(\App\Models\Marketplace\ProviderProfile::class, \App\Policies\Marketplace\ProviderProfilePolicy::class);
        Gate::policy(\App\Models\Marketplace\ServiceEnquiry::class, \App\Policies\Marketplace\ServiceEnquiryPolicy::class);
        Gate::policy(\App\Models\Marketplace\ServiceBooking::class, \App\Policies\Marketplace\ServiceBookingPolicy::class);

        // Apply admin-configured SMTP settings at runtime.
        try {
            if (Schema::hasTable('settings')) {
                AdminController::applyMailConfig();
            }
        } catch (\Throwable) {
            // Database not ready (e.g. during install) — ignore.
        }
    }
}
