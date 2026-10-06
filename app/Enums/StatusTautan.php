<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusTautan: string implements HasColor, HasLabel
{
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Aktif = 'aktif';
    case Dinonaktifkan = 'dinonaktifkan';
    case Diblokir = 'diblokir';
    case Ditolak = 'ditolak';

    public function getLabel(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'Menunggu persetujuan',
            self::Aktif => 'Aktif',
            self::Dinonaktifkan => 'Dinonaktifkan',
            self::Diblokir => 'Diblokir',
            self::Ditolak => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'warning',
            self::Aktif => 'success',
            self::Dinonaktifkan => 'gray',
            self::Diblokir => 'danger',
            self::Ditolak => 'danger',
        };
    }
}
