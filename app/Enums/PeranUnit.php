<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PeranUnit: string implements HasColor, HasLabel
{
    case Pengelola = 'pengelola';
    case Anggota = 'anggota';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pengelola => 'Pengelola',
            self::Anggota => 'Anggota',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pengelola => 'primary',
            self::Anggota => 'gray',
        };
    }
}
