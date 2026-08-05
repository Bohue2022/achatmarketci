<?php

namespace App\Enums;

enum Condition: string
{
    case Neuf = 'neuf';
    case Occasion = 'occasion';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}