<?php

namespace App\Enums;

enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Pending => 'En attente de validation',
            self::Published => 'Publiée',
            self::Rejected => 'Refusée',
            self::Expired => 'Expirée',
            self::Suspended => 'Suspendue',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}