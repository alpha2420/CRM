<?php

use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Webhooks\GoogleLeadFormController;
use App\Http\Controllers\Webhooks\MetaWebhookController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/leads', LeadCaptureController::class)
    ->middleware(['api.key', 'throttle:lead-capture'])
    ->name('api.leads.store');

/*
| Incoming webhooks. Each verifies its own signature.
*/
Route::post('/webhooks/razorpay', RazorpayWebhookController::class)->name('webhooks.razorpay');
Route::get('/webhooks/meta/{key}', [MetaWebhookController::class, 'verify'])->name('webhooks.meta.verify');
Route::post('/webhooks/meta/{key}', [MetaWebhookController::class, 'receive'])->name('webhooks.meta');
Route::post('/webhooks/google/{key}', GoogleLeadFormController::class)->name('webhooks.google');
