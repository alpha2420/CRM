<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('whatsapp_messages')]
#[Fillable(['direction', 'wa_message_id', 'phone', 'type', 'body', 'template_name', 'status', 'error', 'read_at'])]
class WhatsAppMessage extends Model
{
    use BelongsToOrganization;

    public const IN = 'in';

    public const OUT = 'out';

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === self::IN;
    }
}
