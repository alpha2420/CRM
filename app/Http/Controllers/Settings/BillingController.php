<?php

namespace App\Http\Controllers\Settings;

use App\Billing\PlanCatalog;
use App\Billing\RazorpayGateway;
use App\Billing\SubscriptionManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function show(Request $request, PlanCatalog $plans, RazorpayGateway $gateway): View
    {
        return view('settings.billing', [
            'organization' => $request->user()->organization,
            'plans' => $plans->paid(),
            'paymentsEnabled' => $gateway->isConfigured(),
        ]);
    }

    public function subscribe(Request $request, string $plan, PlanCatalog $plans, SubscriptionManager $subscriptions, RazorpayGateway $gateway): RedirectResponse
    {
        $plan = $plans->find($plan);
        abort_if($plan === null || ! $plan->isPaid(), 404);

        if (! $gateway->isConfigured() || $plan->razorpayPlanId === null) {
            return back()->withErrors(['plan' => 'Online payment is not set up yet. Please contact support to upgrade.']);
        }

        $organization = $request->user()->organization;

        if ($organization->seatsUsed() > $plan->maxUsers) {
            return back()->withErrors(['plan' => "{$plan->name} allows {$plan->maxUsers} users and you have {$organization->seatsUsed()} active. Deactivate some users first."]);
        }

        try {
            $paymentUrl = $subscriptions->subscribe($organization, $plan);
        } catch (RequestException $e) {
            report($e);

            return back()->withErrors(['plan' => 'The payment provider did not respond. Please try again.']);
        }

        return $paymentUrl !== null
            ? redirect()->away($paymentUrl)
            : back()->with('status', "Switched to {$plan->name}.");
    }

    public function cancel(Request $request, SubscriptionManager $subscriptions): RedirectResponse
    {
        $organization = $request->user()->organization;
        abort_if($organization->razorpay_subscription_id === null, 404);

        try {
            $subscriptions->cancel($organization);
        } catch (RequestException $e) {
            report($e);

            return back()->withErrors(['plan' => 'The payment provider did not respond. Please try again.']);
        }

        return back()->with('status', 'Subscription cancelled. You keep access until the end of the paid period.');
    }
}
