<?php

namespace App\Enums;

enum Transmission: string
{
    case Manuelle = 'manuelle';
    case Automatique = 'automatique';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}