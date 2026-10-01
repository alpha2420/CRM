<?php

namespace App\Enums;

/**
 * Every pipeline status falls into one of three buckets, which is what
 * reports and the won/lost views rely on (status names are user-defined).
 */
enum StatusType: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
