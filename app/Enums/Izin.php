<?php

namespace App\Enums;

/** Daftar permission PRD §4.2 (`<modul>.<aksi>`). */
enum Izin: string
{
    case TautanLihat = 'tautan.lihat';
    case TautanBuat = 'tautan.buat';
    case TautanSlugKustom = 'tautan.slug-kustom';
    case TautanUbah = 'tautan.ubah';
    case TautanNonaktifkan = 'tautan.nonaktifkan';
    case TautanHapus = 'tautan.hapus';
    case TautanTransfer = 'tautan.transfer';
    case TautanSetujui = 'tautan.setujui';
    case TautanBlokir = 'tautan.blokir';
    case TautanAturLanjutan = 'tautan.atur-lanjutan';
    case TautanTanpaKuota = 'tautan.tanpa-kuota';
    case TautanImpor = 'tautan.impor';
    case AnalitikLihat = 'analitik.lihat';
    case AnalitikLihatAgregat = 'analitik.lihat-agregat';
    case AnalitikEkspor = 'analitik.ekspor';
    case KunjunganLihatRinci = 'kunjungan.lihat-rinci';
    case PenggunaLihat = 'pengguna.lihat';
    case PenggunaKelola = 'pengguna.kelola';
    case PenggunaAturPeranAdmin = 'pengguna.atur-peran-admin';
    case UnitLihat = 'unit.lihat';
    case UnitKelola = 'unit.kelola';
    case UnitKelolaAnggota = 'unit.kelola-anggota';
    case AksesProses = 'akses.proses';
    case ModerasiKelola = 'moderasi.kelola';
    case SlugTerlarangKelola = 'slug-terlarang.kelola';
    case AturanDomainKelola = 'aturan-domain.kelola';
    case PengaturanKelola = 'pengaturan.kelola';
    case HorizonLihat = 'horizon.lihat';
    case AuditLihat = 'audit.lihat';
    case LogLoginLihat = 'log-login.lihat';
}
