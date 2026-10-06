<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KategoriLaporan: string implements HasLabel
{
    case Phishing = 'phishing';
    case Malware = 'malware';
    case Judi = 'judi';
    case Spam = 'spam';
    case KontenTidakPantas = 'konten_tidak_pantas';
    case PelanggaranHak = 'pelanggaran_hak';
    case TujuanMati = 'tujuan_mati';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Phishing => 'Phishing / penipuan',
            self::Malware => 'Malware / perangkat lunak berbahaya',
            self::Judi => 'Judi daring',
            self::Spam => 'Spam',
            self::KontenTidakPantas => 'Konten tidak pantas',
            self::PelanggaranHak => 'Pelanggaran hak',
            self::TujuanMati => 'Tujuan tidak dapat dibuka',
            self::Lainnya => 'Lainnya',
        };
    }

    /** Kategori yang dihitung untuk blokir otomatis (BR-37). */
    public function memicuBlokirOtomatis(): bool
    {
        return in_array($this, [self::Phishing, self::Malware, self::Judi], true);
    }
}
