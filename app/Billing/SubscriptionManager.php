<?php

namespace App\Billing;

use App\Models\Organization;
use Illuminate\Support\Carbon;

/**
 * Applies subscription changes to an organization, whether they come from
 * the customer (subscribe / change / cancel), a Razorpay webhook, or the
 * platform owner recording an offline payment.
 */
final class SubscriptionManager
{
    public function __construct(
        private readonly RazorpayGateway $gateway,
        private readonly PlanCatalog $plans,
    ) {}

    /**
     * Returns the Razorpay page where the customer authorises payment, or
     * null when an existing subscription was switched in place.
     */
    public function subscribe(Organization $organization, Plan $plan): ?string
    {
        if ($organization->razorpay_subscription_id && $organization->hasPaidAccess() && $organization->subscription_status !== 'cancelled') {
            $this->gateway->changePlan($organization->razorpay_subscription_id, $plan);
            $organization->forceFill(['plan' => $plan->key])->save();

            return null;
        }

        $subscription = $this->gateway->createSubscription($organization, $plan);
        $organization->forceFill([
            'razorpay_subscription_id' => $subscription['id'],
            'subscription_status' => $subscription['status'],
        ])->save();

        return $subscription['short_url'];
    }

    public function cancel(Organization $organization): void
    {
        $this->gateway->cancelAtPeriodEnd($organization->razorpay_subscription_id);
        $organization->forceFill(['subscription_status' => 'cancelled'])->save();
    }

    /**
     * @param  array<string, mixed>  $subscription  Razorpay subscription entity
     */
    public function syncFromRazorpay(array $subscription): void
    {
        $organization = Organization::query()
            ->where('razorpay_subscription_id', $subscription['id'] ?? '')
            ->orWhere('id', (int) ($subscription['notes']['organization_id'] ?? 0))
            ->first();

        if ($organization === null) {
            return;
        }

        $planKey = $subscription['notes']['plan'] ?? null;
        $status = (string) ($subscription['status'] ?? '');
        $attributes = [
            'razorpay_subscription_id' => $subscription['id'],
            'subscription_status' => $status,
        ];

        if (isset($subscription['current_end'])) {
            $attributes['current_period_end'] = Carbon::createFromTimestamp((int) $subscription['current_end']);
        }

        // The plan switches once the first payment is authorised.
        if (in_array($status, ['authenticated', 'active'], true) && $planKey && $this->plans->find($planKey)) {
            $attributes['plan'] = $planKey;
        }

        $organization->forceFill($attributes)->save();
    }

    /**
     * Offline payment (bank transfer, UPI) recorded by the platform owner.
     */
    public function grantManually(Organization $organization, Plan $plan, Carbon $paidUntil): void
    {
        $organization->forceFill([
            'plan' => $plan->key,
            'subscription_status' => 'manual',
            'current_period_end' => $paidUntil->endOfDay(),
        ])->save();
    }

    public function extendTrial(Organization $organization, int $days): void
    {
        $from = $organization->trial_ends_at?->isFuture() ? $organization->trial_ends_at : now();

        $organization->forceFill([
            'plan' => PlanCatalog::TRIAL,
            'trial_ends_at' => $from->copy()->addDays($days),
            'trial_reminder_stage' => 0,
        ])->save();
    }
}
