<?php

namespace App\Consent;

enum ConsentAction: string
{
    case Given = 'given';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Given => 'Agreed to be contacted',
            self::Withdrawn => 'Asked not to be contacted',
        };
    }
}
