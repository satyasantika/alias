<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPermintaanAkses: string implements HasColor, HasLabel
{
    case MenungguVerifikasiSurel = 'menunggu_verifikasi_surel';
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Kedaluwarsa = 'kedaluwarsa';

    public function getLabel(): string
    {
        return match ($this) {
            self::MenungguVerifikasiSurel => 'Menunggu verifikasi surel',
            self::Menunggu => 'Menunggu keputusan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Kedaluwarsa => 'Kedaluwarsa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MenungguVerifikasiSurel => 'gray',
            self::Menunggu => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
            self::Kedaluwarsa => 'gray',
        };
    }

    /** Status yang masih membuka permintaan (satu surel hanya boleh punya satu). */
    public function terbuka(): bool
    {
        return in_array($this, [self::MenungguVerifikasiSurel, self::Menunggu], true);
    }
}
