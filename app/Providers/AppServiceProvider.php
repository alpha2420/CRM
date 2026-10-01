<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Ai\ClaudeInsightGenerator;
use App\Ai\InsightGenerator;
use App\Models\User;
use App\Support\LocalTime;
use App\Tenancy\TenantContext;
use App\View\Composers\NavigationComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request / job, never shared between them.
        $this->app->scoped(TenantContext::class);

        $this->app->bind(InsightGenerator::class, fn () => new ClaudeInsightGenerator(
            new AnthropicClient(
                apiKey: (string) config('services.anthropic.api_key'),
                requestOptions: ['timeout' => 60, 'maxRetries' => 2],
            ),
            (string) config('services.anthropic.model'),
        ));
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Behind a load balancer / proxy: trust it for the client IP and HTTPS.
        if (filled(config('crm.trusted_proxies'))) {
            TrustProxies::at(config('crm.trusted_proxies') === '*' ? '*' : explode(',', config('crm.trusted_proxies')));
        }

        if (config('crm.force_https')) {
            URL::forceScheme('https');
        }

        // $date->local(): the same instant in the workspace's time zone, for display.
        Carbon::macro('local', function () {
            /** @var Carbon $this */
            return LocalTime::of($this);
        });

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('platform', fn (User $user) => $user->isPlatformAdmin());

        View::composer('layouts.app', NavigationComposer::class);

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('web-form', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id').'|'.$request->ip()));

        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));

        RateLimiter::for('lead-capture', fn (Request $request) => Limit::perMinute(60)
            ->by(sha1((string) $request->header('X-Api-Key')).'|'.$request->ip()));
    }
}
