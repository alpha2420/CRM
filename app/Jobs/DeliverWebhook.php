<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Webhooks\UrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * POSTs one event to one webhook, signed with its secret:
 * X-Convera-Signature: sha256=HMAC-SHA256(body, secret). Temporary failures
 * (no connection, 429, 5xx) are retried with growing waits; after too
 * many failures in a row the webhook is switched off.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly int $webhookId, public readonly array $payload) {}

    /** @return list<int> seconds to wait before each retry */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(UrlGuard $guard): void
    {
        $webhook = Webhook::withoutGlobalScopes()->find($this->webhookId);

        if ($webhook === null || ! $webhook->is_active) {
            return;
        }

        $address = $guard->safeAddress($webhook->url);
        if ($address === null) {
            $this->record($webhook, null, 'Blocked: the address is not a public HTTPS one.'); // never retried

            return;
        }

        $body = (string) json_encode($this->payload);

        try {
            $response = Http::withHeaders([
                'X-Convera-Event' => (string) $this->payload['event'],
                'X-Convera-Delivery' => (string) $this->payload['id'],
                'X-Convera-Signature' => 'sha256='.hash_hmac('sha256', $body, (string) $webhook->secret),
                'User-Agent' => config('app.name').'-Webhooks/1.0',
            ])
                ->withBody($body, 'application/json')
                ->timeout(10)
                ->withoutRedirecting()
                // Connect to the address that was checked, whatever DNS says now.
                ->withOptions(['curl' => [CURLOPT_RESOLVE => [$webhook->host().':443:'.(str_contains($address, ':') ? "[{$address}]" : $address)]]])
                ->post($webhook->url);
        } catch (ConnectionException $e) {
            $this->record($webhook, null, 'Could not connect.');

            throw $e; // try again later
        }

        $this->record($webhook, $response->status(), $response->successful() ? null : "The app answered HTTP {$response->status()}.");

        if ($this->isTemporary($response)) {
            throw new RuntimeException("Webhook {$webhook->id} answered {$response->status()}; will retry.");
        }
    }

    private function isTemporary(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    private function record(Webhook $webhook, ?int $status, ?string $error): void
    {
        $failures = $error === null ? 0 : $webhook->failures + 1;

        $webhook->forceFill([
            'last_status' => $status,
            'last_error' => $error,
            'last_delivered_at' => now(),
            'failures' => $failures,
            'is_active' => $failures < Webhook::MAX_FAILURES,
        ])->save();
    }
}
