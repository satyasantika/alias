<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JenisPerangkat: string implements HasLabel
{
    case Desktop = 'desktop';
    case Ponsel = 'ponsel';
    case Tablet = 'tablet';
    case Bot = 'bot';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Desktop => 'Desktop',
            self::Ponsel => 'Ponsel',
            self::Tablet => 'Tablet',
            self::Bot => 'Bot',
            self::Lainnya => 'Lainnya',
        };
    }
}
