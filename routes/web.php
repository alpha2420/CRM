<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LeadActivityController;
use App\Http\Controllers\LeadAiController;
use App\Http\Controllers\LeadBulkController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadLostReasonController;
use App\Http\Controllers\LeadMoveController;
use App\Http\Controllers\LeadPrivacyController;
use App\Http\Controllers\LeadSequenceController;
use App\Http\Controllers\LeadTransferController;
use App\Http\Controllers\LeadWhatsAppController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MyDayController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\AutomationController;
use App\Http\Controllers\Settings\AutopilotController;
use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\CustomFieldController;
use App\Http\Controllers\Settings\DataController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\LeadStatusController;
use App\Http\Controllers\Settings\LostReasonController;
use App\Http\Controllers\Settings\OrganizationController;
use App\Http\Controllers\Settings\PrivacyController;
use App\Http\Controllers\Settings\RoutingController;
use App\Http\Controllers\Settings\SequenceController;
use App\Http\Controllers\Settings\SourceController;
use App\Http\Controllers\Settings\WebhookController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebFormController;
use App\Http\Controllers\WhatsAppMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/{page}', LegalController::class)->whereIn('page', ['privacy', 'terms'])->name('legal');

// Hosted lead form: public, shareable, embeddable.
Route::get('/f/{key}', [WebFormController::class, 'show'])->name('web-form.show');
Route::post('/f/{key}', [WebFormController::class, 'submit'])->middleware('throttle:web-form')->name('web-form.submit');

/*
| Guests: sign up, log in, reset a forgotten password.
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.store');
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:two-factor');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::view('/help', 'help')->name('help');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/security', [SecurityController::class, 'show'])->name('security.show');
        Route::post('/profile/security/two-factor', [SecurityController::class, 'enable'])->name('security.two-factor.enable');
        Route::post('/profile/security/two-factor/confirm', [SecurityController::class, 'confirm'])->middleware('throttle:6,1')->name('security.two-factor.confirm');
        Route::post('/profile/security/two-factor/recovery-codes', [SecurityController::class, 'recoveryCodes'])->name('security.two-factor.recovery');
        Route::delete('/profile/security/two-factor', [SecurityController::class, 'disable'])->name('security.two-factor.disable');
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
        Route::post('/push-subscriptions/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:5,1')->name('push.test');
        Route::post('/profile/security/sessions/logout-others', [SecurityController::class, 'logoutOthers'])->name('security.sessions.logout-others');

        // Billing stays reachable after a plan ends, so admins can renew.
        Route::middleware('can:admin')->prefix('settings')->name('settings.')->group(function () {
            Route::get('billing', [BillingController::class, 'show'])->name('billing');
            Route::post('billing/subscribe/{plan}', [BillingController::class, 'subscribe'])->name('billing.subscribe');
            Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        });

        // The SaaS operator's panel.
        Route::middleware('can:platform')->prefix('platform')->name('platform.')->group(function () {
            Route::get('/', [PlatformController::class, 'index'])->name('index');
            Route::get('workspaces/{organization}', [PlatformController::class, 'show'])->name('show');
            Route::post('workspaces/{organization}/suspend', [PlatformController::class, 'suspend'])->name('suspend');
            Route::post('workspaces/{organization}/extend-trial', [PlatformController::class, 'extendTrial'])->name('extend-trial');
            Route::post('workspaces/{organization}/grant-plan', [PlatformController::class, 'grantPlan'])->name('grant-plan');
        });

        /*
        | The CRM itself: needs a live trial or subscription.
        */
        Route::middleware(['subscribed', 'two-factor'])->group(function () {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::get('/today', MyDayController::class)->name('today');
            Route::get('/search', SearchController::class)->middleware('throttle:120,1')->name('search');
            Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
            Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
            Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
            Route::post('/onboarding/dismiss', [DashboardController::class, 'dismissOnboarding'])->name('onboarding.dismiss');

            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/availability', AvailabilityController::class)->name('availability');
            Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
            Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

            Route::middleware('feature:whatsapp')->group(function () {
                Route::get('/inbox', InboxController::class)->name('inbox');
                Route::get('/leads/{lead}/whatsapp', [LeadWhatsAppController::class, 'thread'])->name('leads.whatsapp.thread');
                Route::post('/leads/{lead}/whatsapp', [LeadWhatsAppController::class, 'send'])->name('leads.whatsapp.send');
                Route::get('/messages/{message}/file', WhatsAppMediaController::class)->name('messages.file');
            });

            // Admin-only. Import/export come before the lead resource so
            // "/leads/import" is not mistaken for a lead id.
            Route::middleware('can:admin')->group(function () {
                Route::get('/leads/import', [LeadTransferController::class, 'create'])->name('leads.import');
                Route::post('/leads/import', [LeadTransferController::class, 'store']);
                Route::get('/leads/export', [LeadTransferController::class, 'export'])->name('leads.export');
                Route::get('/leads/import-template', [LeadTransferController::class, 'template'])->name('leads.template');
                Route::get('/leads/{lead}/data', [LeadPrivacyController::class, 'export'])->name('leads.data');
                Route::post('/leads/{lead}/erase', [LeadPrivacyController::class, 'erase'])->name('leads.erase');

                Route::post('/users/invitations', [InvitationController::class, 'store'])->middleware('throttle:20,1')->name('invitations.store');
                Route::delete('/users/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
                Route::resource('users', UserController::class)->except('show');
                Route::get('/reports', ReportController::class)->name('reports');

                Route::prefix('settings')->name('settings.')->group(function () {
                    Route::resource('statuses', LeadStatusController::class)->only(['index', 'store', 'update', 'destroy']);
                    Route::resource('sources', SourceController::class)->only(['index', 'store', 'update', 'destroy']);
                    Route::resource('lost-reasons', LostReasonController::class)->only(['index', 'store', 'update', 'destroy']);
                    Route::get('workspace', [OrganizationController::class, 'edit'])->name('organization.edit');
                    Route::put('workspace', [OrganizationController::class, 'update'])->name('organization.update');
                    Route::post('workspace/api-key', [OrganizationController::class, 'regenerateApiKey'])->name('organization.api-key');

                    Route::get('autopilot', [AutopilotController::class, 'edit'])->name('autopilot.edit');
                    Route::put('autopilot', [AutopilotController::class, 'update'])->name('autopilot.update');

                    Route::get('routing', [RoutingController::class, 'index'])->name('routing.index');
                    Route::put('routing/limit', [RoutingController::class, 'updateLimit'])->name('routing.limit');
                    Route::post('routing/people/{user}/availability', [RoutingController::class, 'availability'])->name('routing.availability');
                    Route::get('routing/rules/create', [RoutingController::class, 'create'])->name('routing.create');
                    Route::post('routing/rules', [RoutingController::class, 'store'])->name('routing.store');
                    Route::get('routing/rules/{rule}/edit', [RoutingController::class, 'edit'])->name('routing.edit');
                    Route::put('routing/rules/{rule}', [RoutingController::class, 'update'])->name('routing.update');
                    Route::post('routing/rules/{rule}/toggle', [RoutingController::class, 'toggle'])->name('routing.toggle');
                    Route::post('routing/rules/{rule}/move/{direction}', [RoutingController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('routing.move');
                    Route::delete('routing/rules/{rule}', [RoutingController::class, 'destroy'])->name('routing.destroy');

                    Route::get('activity', [AuditLogController::class, 'index'])->name('activity');
                    Route::get('privacy', [PrivacyController::class, 'edit'])->name('privacy.edit');
                    Route::put('privacy', [PrivacyController::class, 'update'])->name('privacy.update');
                    Route::post('data/export', [DataController::class, 'export'])->middleware('throttle:3,10')->name('data.export');
                    Route::delete('workspace', [DataController::class, 'destroy'])->name('workspace.destroy');
                    Route::resource('custom-fields', CustomFieldController::class)->only(['index', 'store', 'update', 'destroy']);

                    Route::middleware('feature:automations')->group(function () {
                        Route::resource('automations', AutomationController::class)->except('show');
                        Route::post('automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');
                        Route::resource('sequences', SequenceController::class)->except('show');
                        Route::post('sequences/{sequence}/toggle', [SequenceController::class, 'toggle'])->name('sequences.toggle');
                    });

                    Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
                    Route::post('webhooks', [WebhookController::class, 'store'])->middleware('throttle:20,1')->name('webhooks.store');
                    Route::post('webhooks/{webhook}/toggle', [WebhookController::class, 'toggle'])->name('webhooks.toggle');
                    Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test'])->middleware('throttle:10,1')->name('webhooks.test');
                    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

                    Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
                    Route::post('integrations/whatsapp/templates', [IntegrationController::class, 'syncTemplates'])->name('integrations.templates');
                    Route::post('integrations/{type}/test', [IntegrationController::class, 'test'])->middleware('throttle:10,1')->name('integrations.test');
                    Route::get('integrations/{type}', [IntegrationController::class, 'edit'])->name('integrations.edit');
                    Route::put('integrations/{type}', [IntegrationController::class, 'update'])->name('integrations.update');
                    Route::delete('integrations/{type}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
                });
            });

            Route::post('/leads/bulk', LeadBulkController::class)->name('leads.bulk');
            Route::resource('leads', LeadController::class);
            Route::post('/leads/{lead}/activities', [LeadActivityController::class, 'store'])->name('leads.activities.store');
            Route::patch('/leads/{lead}/status', LeadMoveController::class)->name('leads.move');
            Route::patch('/leads/{lead}/lost-reason', LeadLostReasonController::class)->name('leads.lost-reason');
            Route::post('/leads/{lead}/consent', [LeadPrivacyController::class, 'consent'])->name('leads.consent');
            Route::post('/leads/{lead}/appointments', [AppointmentController::class, 'store'])->name('leads.appointments.store');
            Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
            Route::middleware('feature:automations')->group(function () {
                Route::post('/leads/{lead}/sequence', [LeadSequenceController::class, 'store'])->name('leads.sequence.start');
                Route::delete('/leads/{lead}/sequence', [LeadSequenceController::class, 'destroy'])->name('leads.sequence.stop');
            });
            Route::post('/leads/{lead}/ai', LeadAiController::class)->middleware(['feature:ai', 'throttle:ai'])->name('leads.ai');
        });
    });
});
