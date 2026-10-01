<?php

namespace App\Billing;

use App\Models\Organization;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Razorpay Subscriptions API. The customer pays on
 * Razorpay's hosted page (short_url); the result arrives by webhook.
 */
final class RazorpayGateway
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    /** Monthly cycles to authorise up front (Razorpay requires a count). */
    private const TOTAL_CYCLES = 120;

    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    /**
     * @return array{id: string, short_url: string, status: string}
     */
    public function createSubscription(Organization $organization, Plan $plan): array
    {
        if ($plan->razorpayPlanId === null) {
            throw new RuntimeException("Plan [{$plan->key}] has no Razorpay plan id configured.");
        }

        return $this->client()->post('/subscriptions', [
            'plan_id' => $plan->razorpayPlanId,
            'total_count' => self::TOTAL_CYCLES,
            'customer_notify' => 1,
            'notes' => ['organization_id' => (string) $organization->id, 'plan' => $plan->key],
        ])->throw()->json();
    }

    public function changePlan(string $subscriptionId, Plan $plan): void
    {
        $this->client()->patch("/subscriptions/{$subscriptionId}", [
            'plan_id' => $plan->razorpayPlanId,
            'schedule_change_at' => 'now',
            'customer_notify' => 1,
        ])->throw();
    }

    public function cancelAtPeriodEnd(string $subscriptionId): void
    {
        $this->client()->post("/subscriptions/{$subscriptionId}/cancel", [
            'cancel_at_cycle_end' => 1,
        ])->throw();
    }

    /**
     * Razorpay signs the raw body with HMAC-SHA256 using the webhook secret.
     */
    public function hasValidSignature(string $payload, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withBasicAuth((string) config('services.razorpay.key_id'), (string) config('services.razorpay.key_secret'))
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
