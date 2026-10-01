<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Agent = 'agent';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
