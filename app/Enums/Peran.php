<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Peran: string implements HasLabel
{
    case SuperAdmin = 'super-admin';
    case AdminAlias = 'admin-alias';
    case PengelolaUnit = 'pengelola-unit';
    case Pengguna = 'pengguna';
    case Pemantau = 'pemantau';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::AdminAlias => 'Admin Alias',
            self::PengelolaUnit => 'Pengelola unit',
            self::Pengguna => 'Pengguna',
            self::Pemantau => 'Pemantau',
        };
    }

    /** Peran yang hanya boleh diberikan oleh pemegang pengguna.atur-peran-admin (BR-33). */
    public function peranAdmin(): bool
    {
        return in_array($this, [self::SuperAdmin, self::AdminAlias], true);
    }

    /** @return list<Izin> */
    public function izin(): array
    {
        $semua = Izin::cases();

        return match ($this) {
            self::SuperAdmin => $semua,
            self::AdminAlias => array_values(array_filter($semua, fn (Izin $i) => ! in_array($i, [
                Izin::PenggunaAturPeranAdmin, Izin::PengaturanKelola, Izin::HorizonLihat, Izin::LogLoginLihat,
            ], true))),
            self::PengelolaUnit => [
                Izin::TautanLihat, Izin::TautanBuat, Izin::TautanUbah, Izin::TautanNonaktifkan, Izin::TautanHapus,
                Izin::TautanSlugKustom, Izin::TautanTransfer,
                Izin::AnalitikLihat, Izin::AnalitikLihatAgregat, Izin::AnalitikEkspor, Izin::KunjunganLihatRinci,
                Izin::PenggunaLihat,
                Izin::UnitLihat, Izin::UnitKelolaAnggota,
            ],
            self::Pengguna => [
                Izin::TautanLihat, Izin::TautanBuat, Izin::TautanUbah, Izin::TautanNonaktifkan, Izin::TautanHapus,
                Izin::TautanSlugKustom, Izin::TautanTransfer,
                Izin::AnalitikLihat, Izin::AnalitikEkspor, Izin::KunjunganLihatRinci,
                Izin::UnitLihat,
            ],
            self::Pemantau => [
                Izin::AnalitikLihatAgregat, Izin::AnalitikEkspor, Izin::UnitLihat,
            ],
        };
    }
}
