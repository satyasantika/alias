<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusEfektifTautan: string implements HasColor, HasLabel
{
    case DapatDialihkan = 'dapat_dialihkan';
    case Terjadwal = 'terjadwal';
    case Kedaluwarsa = 'kedaluwarsa';
    case Habis = 'habis';
    case TidakTersedia = 'tidak_tersedia';

    public function getLabel(): string
    {
        return match ($this) {
            self::DapatDialihkan => 'Dapat dialihkan',
            self::Terjadwal => 'Terjadwal',
            self::Kedaluwarsa => 'Kedaluwarsa',
            self::Habis => 'Kuota habis',
            self::TidakTersedia => 'Tidak tersedia',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DapatDialihkan => 'success',
            self::Terjadwal => 'info',
            self::Kedaluwarsa => 'gray',
            self::Habis => 'gray',
            self::TidakTersedia => 'danger',
        };
    }
}
