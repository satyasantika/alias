<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JenisKepemilikan: string implements HasColor, HasLabel
{
    case Pribadi = 'pribadi';
    case Unit = 'unit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pribadi => 'Pribadi',
            self::Unit => 'Unit',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pribadi => 'gray',
            self::Unit => 'primary',
        };
    }
}
