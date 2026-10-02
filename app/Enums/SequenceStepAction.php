<?php

namespace App\Enums;

use App\Sequences\Steps\RemindOwner;
use App\Sequences\Steps\SendTemplate;
use App\Sequences\Steps\StepHandler;

/**
 * What one step of a sequence does. Each action has its own handler class.
 */
enum SequenceStepAction: string
{
    case WhatsAppTemplate = 'whatsapp_template';
    case RemindOwner = 'remind_owner';

    public function label(): string
    {
        return match ($this) {
            self::WhatsAppTemplate => 'Send a WhatsApp template',
            self::RemindOwner => 'Remind the owner to follow up',
        };
    }

    /** @return class-string<StepHandler> */
    public function handler(): string
    {
        return match ($this) {
            self::WhatsAppTemplate => SendTemplate::class,
            self::RemindOwner => RemindOwner::class,
        };
    }
}
