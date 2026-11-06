<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View as ViewFacade;
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
        Gate::before(function (User $user, string $ability): ?bool {
            return in_array($ability, User::ADMIN_PERMISSIONS, true)
                ? $user->hasAdminPermission($ability)
                : null;
        });

        ViewFacade::composer(['layouts.app', 'layouts.admin'], function (View $view): void {
            $view->with('applicationName', config('app.name'));
        });

        ViewFacade::composer('layouts.app', function (View $view): void {
            $user = auth()->user();
            $view->with('unreadNotificationCount', $user?->unreadNotifications()->count() ?? 0);
        });
    }
}
