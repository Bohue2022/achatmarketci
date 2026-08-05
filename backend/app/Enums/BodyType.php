<?php

namespace App\Enums;

enum BodyType: string
{
    case Berline = 'berline';
    case SUV = 'suv';
    case Pickup = 'pickup';
    case Utilitaire = 'utilitaire';
    case Offroad = '4x4';
    case Coupe = 'coupe';
    case Cabriolet = 'cabriolet';
    case Monospace = 'monospace';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}