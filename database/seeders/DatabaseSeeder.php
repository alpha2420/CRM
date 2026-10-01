<?php

namespace Database\Seeders;

use App\Enums\IntegrationType;
use App\Enums\Role;
use App\Enums\StatusType;
use App\Models\Automation;
use App\Models\CustomField;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\LeadService;
use App\Services\OrganizationRegistrar;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Local demo workspace. Log in as admin@demo.test / password (also a
 * platform admin when PLATFORM_ADMIN_EMAILS=admin@demo.test).
 *
 * The WhatsApp connection uses placeholder credentials so the inbox has
 * something to show; replace them in Settings → Integrations to really send.
 */
class DatabaseSeeder extends Seeder
{
    public function run(OrganizationRegistrar $registrar, LeadService $leads, TenantContext $tenant): void
    {
        $admin = $registrar->register('Demo Company', 'Demo Admin', 'admin@demo.test', 'password');
        $admin->markEmailAsVerified();
        $organization = $admin->organization;
        $tenant->set($organization->id);

        $agents = collect(['Asha Agent' => 'asha@demo.test', 'Ravi Agent' => 'ravi@demo.test'])
            ->map(fn ($email, $name) => tap($organization->users()->create(['name' => $name, 'email' => $email, 'password' => 'password', 'role' => Role::Agent]))->markEmailAsVerified());

        CustomField::create(['label' => 'Interested in', 'key' => 'interested_in', 'type' => 'select', 'options' => ['Basic plan', 'Pro plan', 'Enterprise'], 'sort_order' => 1]);
        CustomField::create(['label' => 'Budget', 'key' => 'budget', 'type' => 'number', 'sort_order' => 2]);

        $statuses = LeadStatus::query()->ordered()->get();
        $open = $statuses->where('type', StatusType::Open)->values();
        $sources = $organization->sources()->get();

        foreach (Lead::factory()->count(60)->make(['organization_id' => $organization->id]) as $i => $sample) {
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(0, 60))->setTime(fake()->numberBetween(9, 19), fake()->numberBetween(0, 59));
            $lead = $leads->create($organization, [
                'name' => $sample->name,
                'phone' => $sample->phone,
                'email' => $sample->email,
                'company' => $sample->company,
                'city' => $sample->city,
                'source_id' => $sources->random()->id,
                'priority' => fake()->randomElement(['high', 'medium', 'low']),
                'value' => fake()->optional(0.6)->numberBetween(5, 200) * 1000 ?: null,
                'custom_values' => ['interested_in' => fake()->randomElement(['Basic plan', 'Pro plan', 'Enterprise']), 'budget' => (string) (fake()->numberBetween(10, 500) * 1000)],
            ], $admin);
            $lead->forceFill(['created_at' => $createdAt])->saveQuietly();

            if ($i % 4 !== 0) {
                $contactedAt = $createdAt->copy()->addMinutes(fake()->numberBetween(5, 600));
                $status = $i % 7 === 0 ? $statuses->firstWhere('type', StatusType::Won) : ($i % 11 === 0 ? $statuses->firstWhere('type', StatusType::Lost) : $open->random());
                $activity = $leads->logActivity($lead, $lead->assignee ?? $admin, [
                    'status_id' => $status->id,
                    'note' => fake()->randomElement(['Called, interested — sending details.', 'No answer, will try again.', 'Asked for pricing on WhatsApp.', 'Demo scheduled.', 'Decided to go ahead!']),
                    'next_follow_up_at' => $status->type === StatusType::Open ? now()->addDays(fake()->numberBetween(-2, 6))->setTime(11, 0)->toDateTimeString() : null,
                ]);
                $activity->forceFill(['created_at' => $contactedAt])->save();
                $lead->forceFill(['first_contacted_at' => $contactedAt, 'last_activity_at' => $contactedAt, 'closed_at' => $status->type === StatusType::Open ? null : $contactedAt->copy()->addDays(2)])->saveQuietly();
            }
        }

        $this->seedWhatsApp($organization);
        $this->seedWebForm($organization);

        Automation::create([
            'name' => 'Follow up website leads fast',
            'trigger' => 'lead_created',
            'conditions' => ['source_id' => $sources->firstWhere('name', 'Website')->id],
            'actions' => ['follow_up_in_hours' => 2],
        ]);

        Lead::query()->latest('id')->first()->forceFill([
            'ai_insight' => [
                'summary' => 'Interested in the Pro plan for a 12-person sales team; asked about WhatsApp integration and pricing.',
                'temperature' => 'hot',
                'reason' => 'Asked for pricing and a demo slot this week.',
                'next_step' => 'Send the Pro plan pricing and offer two demo slots tomorrow.',
                'suggested_message' => 'Hi! Thanks for your interest in our Pro plan. Here is the pricing — would tomorrow 11am or 4pm work for a quick demo?',
            ],
            'ai_insight_at' => now()->subHour(),
        ])->saveQuietly();
    }

    private function seedWhatsApp($organization): void
    {
        $whatsapp = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => [
            'phone_number_id' => 'DEMO_PHONE_ID', 'waba_id' => 'DEMO_WABA_ID', 'access_token' => 'demo-token',
            'app_secret' => 'demo-secret', 'verify_token' => 'demo-verify', 'default_country_code' => '91',
        ]]);
        $whatsapp->organization_id = $organization->id;
        $whatsapp->save();

        $template = new WhatsAppTemplate(['name' => 'welcome', 'language' => 'en', 'category' => 'UTILITY', 'status' => 'APPROVED', 'body' => 'Hi {{1}}, thanks for contacting {{2}}! How can we help you today?', 'variables' => 2]);
        $template->organization_id = $organization->id;
        $template->save();

        $conversations = [
            ['Hi, I saw your ad. What does the Pro plan cost?', 'Hi! The Pro plan is ₹4,999/month for up to 25 users. Shall I set up a demo?', 'Yes please, tomorrow works.'],
            ['Do you support WhatsApp?', 'Yes — the official WhatsApp Cloud API, right inside the CRM.'],
            ['Can I import my Excel leads?'],
        ];

        foreach (Lead::query()->whereNotNull('assigned_to')->latest('id')->limit(3)->get() as $i => $lead) {
            $at = now()->subHours(6 - $i * 2);
            foreach ($conversations[$i] as $j => $text) {
                $message = $lead->whatsappMessages()->make([
                    'direction' => $j % 2 === 0 ? WhatsAppMessage::IN : WhatsAppMessage::OUT,
                    'phone' => ltrim($lead->phone, '+'),
                    'body' => $text,
                    'status' => $j % 2 === 0 ? 'received' : 'read',
                    'read_at' => $j < count($conversations[$i]) - 1 ? $at : null,
                ]);
                $message->organization_id = $organization->id;
                $message->user_id = $j % 2 ? $lead->assigned_to : null;
                $message->created_at = $at->copy()->addMinutes($j * 7);
                $message->save();
            }
            $lead->forceFill(['last_message_at' => $at->copy()->addMinutes(count($conversations[$i]) * 7), 'last_inbound_at' => $at])->saveQuietly();
        }
    }

    private function seedWebForm($organization): void
    {
        $form = new Integration(['type' => IntegrationType::WebForm, 'settings' => [
            'title' => 'Book a free demo', 'button' => 'Book my demo', 'thank_you' => 'Thanks! We will call you within the hour.',
            'ask_email' => true, 'ask_city' => true, 'ask_message' => true,
        ]]);
        $form->organization_id = $organization->id;
        $form->save();
    }
}
