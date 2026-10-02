<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookRequest;
use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Services\AuditLogger;
use App\Webhooks\WebhookPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Settings → Webhooks: send CRM events to other apps.
 */
class WebhookController extends Controller
{
    /** Webhooks per workspace. */
    private const MAX = 10;

    public function index(): View
    {
        return view('settings.webhooks', ['webhooks' => Webhook::query()->latest()->get(), 'max' => self::MAX]);
    }

    public function store(WebhookRequest $request): RedirectResponse
    {
        if (Webhook::query()->count() >= self::MAX) {
            return back()->withErrors(['url' => 'You can have up to '.self::MAX.' webhooks.']);
        }

        $webhook = new Webhook(['url' => $request->validated('url'), 'events' => array_values(array_unique($request->validated('events'))), 'is_active' => true]);
        $webhook->secret = Str::random(40);
        $webhook->save();
        app(AuditLogger::class)->log('webhook.created', "Added a webhook to {$webhook->host()}", $webhook);

        return back()->with('status', 'Webhook added. Use "Send test" to check it.');
    }

    public function toggle(Webhook $webhook): RedirectResponse
    {
        $webhook->forceFill(['is_active' => ! $webhook->is_active, 'failures' => 0])->save();

        return back()->with('status', $webhook->is_active ? 'Webhook turned on.' : 'Webhook paused.');
    }

    /**
     * Deliver a test event right now and report what the other app said.
     */
    public function test(Request $request, Webhook $webhook): RedirectResponse
    {
        try {
            DeliverWebhook::dispatchSync($webhook->id, WebhookPayload::ping($request->user()->organization));
        } catch (Throwable) {
            // The outcome is recorded on the webhook either way.
        }

        $webhook->refresh();

        return $webhook->last_error === null
            ? back()->with('status', "Test delivered: the app answered HTTP {$webhook->last_status}.")
            : back()->withErrors(['test' => "Test failed: {$webhook->last_error}"]);
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $webhook->delete();
        app(AuditLogger::class)->log('webhook.deleted', "Removed the webhook to {$webhook->host()}");

        return back()->with('status', 'Webhook removed.');
    }
}
