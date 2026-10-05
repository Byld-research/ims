<?php

namespace App\Providers;

use App\Models\Site;
use App\Models\Stock;
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

        // Whether the user may record movements at any site; shows the Issue entry in the menu.
        Gate::define('issue-stock', fn (User $user) => Site::query()->active()->get()
            ->contains(fn (Site $site) => $user->can('issue', [Stock::class, $site])));

        View::composer('*', function ($view) {
            if (auth()->check()) {
                $view->with('currentSite', app(CurrentSite::class));
            }
        });
    }
}
