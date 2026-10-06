<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DimensiRekap: string implements HasLabel
{
    case JenisPerangkat = 'jenis_perangkat';
    case Peramban = 'peramban';
    case Os = 'os';
    case PerujukHost = 'perujuk_host';

    public function getLabel(): string
    {
        return match ($this) {
            self::JenisPerangkat => 'Perangkat',
            self::Peramban => 'Peramban',
            self::Os => 'Sistem operasi',
            self::PerujukHost => 'Perujuk',
        };
    }

    /** Nilai pengganti bila data kosong. */
    public function nilaiKosong(): string
    {
        return $this === self::PerujukHost ? '(langsung)' : '(tidak diketahui)';
    }
}
