<?php

namespace App\Integrations;

use App\Enums\Feature;
use App\Enums\IntegrationType;
use App\Events\WhatsAppMessageReceived;
use App\Events\WhatsAppMessageSent;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Notifications\WhatsAppReceivedNotification;
use App\Services\LeadIntake;
use App\Support\WhatsAppNumber;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Two-way WhatsApp via the official Cloud API: queue outgoing messages,
 * record incoming ones (creating leads for new numbers) and track
 * delivery status.
 */
final class WhatsAppService
{
    /** Delivery states only move forward. */
    private const STATUS_RANK = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    public function __construct(
        private readonly MetaGraph $graph,
        private readonly LeadIntake $intake,
    ) {}

    public function integrationFor(Organization $organization): ?Integration
    {
        if (! $organization->canUse(Feature::WhatsApp)) {
            return null;
        }

        return Integration::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('type', IntegrationType::WhatsApp)
            ->where('is_active', true)
            ->first();
    }

    public function sendText(Lead $lead, ?User $user, string $body): WhatsAppMessage
    {
        if (! $lead->whatsappWindowOpen()) {
            throw new DomainException('More than 24 hours have passed since this lead last wrote. Send an approved template instead.');
        }

        return $this->queue($lead, $user, ['type' => 'text', 'body' => $body]);
    }

    /**
     * @param  list<string>  $parameters
     */
    public function sendTemplate(Lead $lead, ?User $user, WhatsAppTemplate $template, array $parameters): WhatsAppMessage
    {
        $body = (string) $template->body;
        foreach (array_values($parameters) as $i => $value) {
            $body = str_replace('{{'.($i + 1).'}}', $value, $body);
        }

        return $this->queue($lead, $user, [
            'type' => 'template',
            'template_name' => $template->name,
            'body' => $body,
            'payload' => ['language' => $template->language, 'parameters' => array_values($parameters)],
        ]);
    }

    /**
     * Called by the queued job: actually talk to WhatsApp.
     */
    public function deliver(WhatsAppMessage $message, array $templatePayload = []): void
    {
        $lead = $message->lead;
        $integration = $this->integrationFor($lead->organization);

        if ($integration === null) {
            $message->update(['status' => 'failed', 'error' => 'WhatsApp is not connected.']);

            return;
        }

        try {
            $waId = $message->type === 'template'
                ? $this->graph->sendTemplate($integration, $message->phone, $message->template_name, $templatePayload['language'] ?? 'en', $templatePayload['parameters'] ?? [])
                : $this->graph->sendText($integration, $message->phone, (string) $message->body);

            $message->update(['status' => 'sent', 'wa_message_id' => $waId, 'error' => null]);
        } catch (RequestException $e) {
            if ($e->response->serverError()) {
                throw $e; // temporary: let the queue retry
            }

            $message->update(['status' => 'failed', 'error' => (string) ($e->response->json('error.message') ?? 'WhatsApp rejected the message.')]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload  WhatsApp Cloud API webhook body
     */
    public function handleWebhook(Integration $integration, array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $names = collect($value['contacts'] ?? [])->pluck('profile.name', 'wa_id');

                foreach ($value['messages'] ?? [] as $incoming) {
                    $this->receive($integration, $incoming, $names[$incoming['from'] ?? ''] ?? null);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->updateStatus($status);
                }
            }
        }
    }

    public function syncTemplates(Integration $integration): int
    {
        $templates = $this->graph->templates($integration);

        DB::transaction(function () use ($integration, $templates) {
            WhatsAppTemplate::query()->delete();

            foreach ($templates as $template) {
                $body = collect($template['components'] ?? [])->firstWhere('type', 'BODY')['text'] ?? '';
                preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

                $model = new WhatsAppTemplate([
                    'name' => $template['name'],
                    'language' => $template['language'],
                    'category' => $template['category'] ?? null,
                    'status' => $template['status'] ?? 'UNKNOWN',
                    'body' => $body,
                    'variables' => $matches[1] ? max(array_map('intval', $matches[1])) : 0,
                ]);
                $model->organization_id = $integration->organization_id;
                $model->save();
            }
        });

        return count($templates);
    }

    /**
     * The latest 100 messages, oldest first.
     *
     * @return Collection<int, WhatsAppMessage>
     */
    public function conversation(Lead $lead): Collection
    {
        return $lead->whatsappMessages()->with('user')->latest('id')->limit(100)->get()->reverse()->values();
    }

    public function markRead(Lead $lead): void
    {
        $lead->whatsappMessages()
            ->where('direction', WhatsAppMessage::IN)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Everything the chat composer needs: the conversation, templates and,
     * when one is picked, its default values.
     *
     * @return array<string, mixed>
     */
    public function composerData(Lead $lead, ?int $templateId): array
    {
        $templates = WhatsAppTemplate::query()->orderBy('name')->get();
        $selected = $templateId ? $templates->firstWhere('id', $templateId) : null;
        $defaults = [];

        if ($selected !== null) {
            foreach ($this->defaultParameters($lead, $selected->variables) as $i => $value) {
                $defaults[$i + 1] = $value;
            }
        }

        return [
            'messages' => $this->conversation($lead),
            'templates' => $templates,
            'selectedTemplate' => $selected,
            'templateDefaults' => $defaults,
        ];
    }

    /**
     * Default template values: {{1}} = lead's first name, {{2}} = company.
     *
     * @return array<int, string>
     */
    public function defaultParameters(Lead $lead, int $count): array
    {
        $defaults = [1 => Str::before(trim($lead->name), ' '), 2 => $lead->organization->name];

        return array_map(fn (int $i) => $defaults[$i] ?? '', $count > 0 ? range(1, $count) : []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function queue(Lead $lead, ?User $user, array $data): WhatsAppMessage
    {
        $integration = $this->integrationFor($lead->organization)
            ?? throw new DomainException('Connect WhatsApp under Settings → Integrations first.');

        $payload = $data['payload'] ?? [];
        unset($data['payload']);

        $message = $lead->whatsappMessages()->make($data + [
            'direction' => WhatsAppMessage::OUT,
            'phone' => WhatsAppNumber::fromPhone($lead->phone, (string) $integration->setting('default_country_code', '91')),
            'status' => 'queued',
        ]);
        $message->organization_id = $lead->organization_id;
        $message->user()->associate($user);
        $message->save();

        // Only a person reaching out counts as the first contact; an
        // automatic welcome message does not.
        $lead->forceFill([
            'last_message_at' => now(),
            'first_contacted_at' => $lead->first_contacted_at ?? ($user ? now() : null),
        ])->saveQuietly();

        SendWhatsAppMessage::dispatch($message->id, $payload)->afterCommit();
        WhatsAppMessageSent::dispatch($lead, $message, $user);

        return $message;
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    private function receive(Integration $integration, array $incoming, ?string $profileName): void
    {
        $waId = (string) ($incoming['from'] ?? '');

        if ($waId === '' || WhatsAppMessage::query()->where('wa_message_id', $incoming['id'] ?? '')->exists()) {
            return;
        }

        $organization = $integration->organization;
        $lead = $organization->leads()
            ->whereIn('phone', WhatsAppNumber::storedVariants($waId, (string) $integration->setting('default_country_code', '91')))
            ->first()
            ?? $this->intake->capture($organization, ['name' => $profileName ?: '+'.$waId, 'phone' => '+'.$waId], IntegrationType::WhatsApp->sourceName())->lead;

        $message = $lead->whatsappMessages()->make([
            'direction' => WhatsAppMessage::IN,
            'wa_message_id' => $incoming['id'] ?? null,
            'phone' => $waId,
            'type' => 'text',
            'body' => $this->textOf($incoming),
            'status' => 'received',
        ]);
        $message->organization_id = $lead->organization_id;
        $message->save();

        $lead->forceFill(['last_message_at' => now(), 'last_inbound_at' => now()])->saveQuietly();
        $lead->assignee?->notify(new WhatsAppReceivedNotification($lead, $message));
        WhatsAppMessageReceived::dispatch($lead, $message);
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    private function textOf(array $incoming): string
    {
        return match ($incoming['type'] ?? 'text') {
            'text' => (string) ($incoming['text']['body'] ?? ''),
            'button' => (string) ($incoming['button']['text'] ?? '[button]'),
            'interactive' => (string) ($incoming['interactive']['button_reply']['title'] ?? $incoming['interactive']['list_reply']['title'] ?? '[reply]'),
            default => '['.($incoming['type'] ?? 'message').']',
        };
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function updateStatus(array $status): void
    {
        $message = WhatsAppMessage::query()->where('wa_message_id', $status['id'] ?? '')->first();
        $new = (string) ($status['status'] ?? '');

        if ($message === null) {
            return;
        }

        if ($new === 'failed') {
            $message->update(['status' => 'failed', 'error' => $status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? 'Delivery failed']);
        } elseif ((self::STATUS_RANK[$new] ?? -1) > (self::STATUS_RANK[$message->status] ?? -1)) {
            $message->update(['status' => $new]);
        }
    }
}
