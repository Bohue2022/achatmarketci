<?php

namespace App\Enums;

enum FuelType: string
{
    case Essence = 'essence';
    case Diesel = 'diesel';
    case Hybride = 'hybride';
    case Electrique = 'electrique';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}