<?php

namespace App\Providers;

use App\Models\Site;
use App\Models\Stock;
use App\Models\User;
use App\Support\CurrentSite;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentSite::class, fn ($app) => new CurrentSite(
            $app['session.store'],
            // An API client is not a user; the API never uses the selected site.
            $app['auth']->user() instanceof User ? $app['auth']->user() : null,
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Passwords: at least 12 characters with letters and digits in production (SPEC 9a).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->letters()->numbers()
            : Password::min(8));

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // API (SPEC 7a): per token, so one busy client cannot slow down the others.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) config('ims.api.per_minute'))
            ->by($request->user() ? 'api-client:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip()));

        Gate::define('switch-site', fn (User $user) => $user->isAdmin());
        Gate::define('view-audit-log', fn (User $user) => $user->isAdmin());

        // Whether the user may record movements at any site; shows the Issue entry in the menu.
        Gate::define('issue-stock', fn (User $user) => Site::query()->active()->get()
            ->contains(fn (Site $site) => $user->can('issue', [Stock::class, $site])));

        Gate::define('set-levels', fn (User $user) => Site::query()->active()->get()
            ->contains(fn (Site $site) => $user->can('setLevels', [Stock::class, $site])));

        View::composer('*', function ($view) {
            if (auth()->check()) {
                $view->with('currentSite', app(CurrentSite::class));
            }
        });
    }
}
