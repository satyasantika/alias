<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JenisSlugTerlarang: string implements HasLabel
{
    case CadanganSistem = 'cadangan_sistem';
    case CadanganKelembagaan = 'cadangan_kelembagaan';
    case TidakPantas = 'tidak_pantas';

    public function getLabel(): string
    {
        return match ($this) {
            self::CadanganSistem => 'Cadangan sistem',
            self::CadanganKelembagaan => 'Cadangan kelembagaan',
            self::TidakPantas => 'Tidak pantas',
        };
    }
}
