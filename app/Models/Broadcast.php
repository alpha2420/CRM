<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'audience', 'values'])]
class Broadcast extends Model
{
    use BelongsToOrganization;

    public const SENDING = 'sending';

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'values' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WhatsAppMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    /**
     * How far it got: messages sent, delivered, read and failed, and how
     * many leads wrote back after it went out.
     *
     * @return array{queued: int, sent: int, delivered: int, read: int, failed: int, replied: int}
     */
    public function results(): array
    {
        $counts = $this->messages()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $count = fn (string ...$statuses) => (int) collect($statuses)->sum(fn (string $status) => $counts[$status] ?? 0);

        return [
            'queued' => $count('queued'),
            'sent' => $count('sent', 'delivered', 'read'),
            'delivered' => $count('delivered', 'read'),
            'read' => $count('read'),
            'failed' => $count('failed'),
            'replied' => $this->started_at === null ? 0 : WhatsAppMessage::query()
                ->where('direction', WhatsAppMessage::IN)
                ->where('created_at', '>=', $this->started_at)
                ->whereIn('lead_id', $this->messages()->select('lead_id'))
                ->distinct()
                ->count('lead_id'),
        ];
    }
}
