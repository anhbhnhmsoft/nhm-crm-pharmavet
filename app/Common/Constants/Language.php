<?php

namespace App\Common\Constants;

enum Language: string
{
    case EN = 'en';
    case VI = 'vi';
    case LO = 'lo'; // Lào

    public function label(): string
    {
        return match ($this) {
            self::EN => 'English',
            self::VI => 'Tiếng Việt',
            self::LO => 'ພາສາລາວ',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(function($case) {
                return [$case->value => $case->label()];
            } )
            ->toArray();
    }
}
