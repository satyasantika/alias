<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PeristiwaLogin: string implements HasColor, HasLabel
{
    case Berhasil = 'berhasil';
    case Gagal = 'gagal';
    case Keluar = 'keluar';
    case Terkunci = 'terkunci';
    case ResetKataSandi = 'reset_kata_sandi';
    case MfaGagal = 'mfa_gagal';
    case DitolakNonaktif = 'ditolak_nonaktif';
    case DitolakDomain = 'ditolak_domain';

    public function getLabel(): string
    {
        return match ($this) {
            self::Berhasil => 'Berhasil',
            self::Gagal => 'Gagal',
            self::Keluar => 'Keluar',
            self::Terkunci => 'Terkunci',
            self::ResetKataSandi => 'Reset kata sandi',
            self::MfaGagal => 'MFA gagal',
            self::DitolakNonaktif => 'Ditolak (nonaktif/terkunci)',
            self::DitolakDomain => 'Ditolak (domain surel)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Berhasil, self::Keluar => 'success',
            self::ResetKataSandi => 'info',
            self::Gagal, self::MfaGagal => 'warning',
            self::Terkunci, self::DitolakNonaktif, self::DitolakDomain => 'danger',
        };
    }
}
