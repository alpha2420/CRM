<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Ai\AiProvider;
use App\Ai\ClaudeInsightGenerator;
use App\Ai\GeminiInsightGenerator;
use App\Ai\InsightGenerator;
use App\Models\User;
use App\Push\PushSender;
use App\Push\WebPushSender;
use App\Services\AuditLogger;
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
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request / job, never shared between them.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(AuditLogger::class);

        $this->app->bind(PushSender::class, WebPushSender::class);

        $this->app->bind(InsightGenerator::class, fn () => match ($provider = AiProvider::current()) {
            AiProvider::Gemini => new GeminiInsightGenerator($provider->apiKey(), $provider->model()),
            AiProvider::Anthropic => new ClaudeInsightGenerator(
                new AnthropicClient(apiKey: $provider->apiKey(), requestOptions: ['timeout' => 60, 'maxRetries' => 2]),
                $provider->model(),
            ),
        });
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

        View::composer(['layouts.app', 'dashboard', 'leads.show', 'partials.contact-tools'], NavigationComposer::class);

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

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));

        // Live passwords: at least 10 characters with letters and numbers,
        // and not found in known data breaches (checked anonymously).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));
    }
}
