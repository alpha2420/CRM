<?php

namespace App\Http\Controllers;

use App\Jobs\SendPushNotification;
use App\Push\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'device' => mb_substr((string) $request->userAgent(), 0, 250),
            ],
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'string']]);
        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $request->string('endpoint')->toString()))->delete();

        return response()->json(['status' => 'unsubscribed']);
    }

    public function test(Request $request, PushSender $sender): JsonResponse
    {
        abort_unless($sender->isConfigured(), 404);

        SendPushNotification::dispatch($request->user()->id, [
            'title' => 'Notifications are on',
            'body' => 'You will get new leads, due follow-ups and WhatsApp messages here.',
            'url' => route('notifications.index'),
        ]);

        return response()->json(['status' => 'sent']);
    }
}
