<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentSite::class, fn ($app) => new CurrentSite(
            $app['session.store'],
            $app['auth']->user(),
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Gate::define('switch-site', fn (User $user) => $user->isAdmin());

        View::composer('*', function ($view) {
            if (auth()->check()) {
                $view->with('currentSite', app(CurrentSite::class));
            }
        });
    }
}
