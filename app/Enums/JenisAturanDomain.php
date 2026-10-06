<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JenisAturanDomain: string implements HasColor, HasLabel
{
    case Blokir = 'blokir';
    case Izinkan = 'izinkan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Blokir => 'Blokir',
            self::Izinkan => 'Izinkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Blokir => 'danger',
            self::Izinkan => 'success',
        };
    }
}
