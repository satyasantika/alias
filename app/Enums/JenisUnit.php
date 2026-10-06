<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JenisUnit: string implements HasLabel
{
    case Fakultas = 'fakultas';
    case Jurusan = 'jurusan';
    case Prodi = 'prodi';
    case UnitKerja = 'unit_kerja';
    case Ormawa = 'ormawa';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fakultas => 'Fakultas',
            self::Jurusan => 'Jurusan',
            self::Prodi => 'Program studi',
            self::UnitKerja => 'Unit kerja',
            self::Ormawa => 'Ormawa',
            self::Lainnya => 'Lainnya',
        };
    }
}
