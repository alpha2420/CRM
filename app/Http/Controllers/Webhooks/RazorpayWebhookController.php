<?php

namespace App\Http\Controllers\Webhooks;

use App\Billing\RazorpayGateway;
use App\Billing\SubscriptionManager;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpayGateway $gateway, SubscriptionManager $subscriptions): Response
    {
        $payload = $request->getContent();

        if (! $gateway->hasValidSignature($payload, (string) $request->header('X-Razorpay-Signature'))) {
            return response('Invalid signature', 400);
        }

        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: sha1($payload));
        $event = (string) $request->input('event');

        DB::transaction(function () use ($eventId, $event, $request, $subscriptions) {
            if (! WebhookEvent::claim('razorpay', $eventId)) {
                return;
            }

            if (str_starts_with($event, 'subscription.')) {
                $subscriptions->syncFromRazorpay((array) $request->input('payload.subscription.entity', []));
            }
        });

        return response('OK');
    }
}
