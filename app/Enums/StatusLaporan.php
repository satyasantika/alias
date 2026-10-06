<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusLaporan: string implements HasColor, HasLabel
{
    case Baru = 'baru';
    case Ditinjau = 'ditinjau';
    case Ditindaklanjuti = 'ditindaklanjuti';
    case Ditolak = 'ditolak';

    public function getLabel(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Ditinjau => 'Ditinjau',
            self::Ditindaklanjuti => 'Ditindaklanjuti',
            self::Ditolak => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Baru => 'danger',
            self::Ditinjau => 'warning',
            self::Ditindaklanjuti => 'success',
            self::Ditolak => 'gray',
        };
    }
}
