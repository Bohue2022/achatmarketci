<?php

namespace App\Enums;

enum Role: string
{
    case User = 'user';
    case Pro = 'pro';
    case Moderator = 'moderator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Particulier',
            self::Pro => 'Professionnel',
            self::Moderator => 'Modérateur',
            self::Admin => 'Administrateur',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}