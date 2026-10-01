<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Dropdown',
        };
    }
}
