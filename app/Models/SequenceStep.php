<?php

namespace App\Models;

use App\Enums\SequenceStepAction;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['day', 'action', 'whatsapp_template_id', 'note'])]
class SequenceStep extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'day' => 'integer',
            'action' => SequenceStepAction::class,
            'whatsapp_template_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Sequence, $this> */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    /** "Day 3: Send the “brochure” template", for lists and history. */
    public function summary(): string
    {
        return match ($this->action) {
            SequenceStepAction::WhatsAppTemplate => 'Send “'.($this->template->name ?? 'a removed template').'” on WhatsApp',
            SequenceStepAction::RemindOwner => 'Remind owner: '.$this->note,
        };
    }
}
