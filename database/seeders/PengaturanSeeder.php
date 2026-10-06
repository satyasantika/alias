<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use Illuminate\Database\Seeder;

/** 03-SKEMA §6.5. Tidak menimpa nilai yang sudah diubah admin. */
class PengaturanSeeder extends Seeder
{
    /** @var list<array{string, string, string, string}> */
    private const BAWAAN = [
        ['kuota_bawaan_pengguna', 'int', '100', 'Kuota tautan pribadi bawaan per pengguna'],
        ['kuota_bawaan_unit', 'int', '500', 'Kuota tautan bawaan per unit'],
        ['slug_kustom_perlu_persetujuan', 'bool', '1', 'Slug kustom pemegang peran pengguna perlu persetujuan'],
        ['mode_domain', 'string', 'bebas', 'bebas atau daftar_putih (domain tujuan)'],
        ['retensi_kunjungan_bulan', 'int', '12', 'Retensi kunjungan manusia (bulan)'],
        ['retensi_kunjungan_bot_hari', 'int', '30', 'Retensi kunjungan bot (hari)'],
        ['retensi_log_login_hari', 'int', '90', 'Retensi log login (hari)'],
        ['ambang_blokir_otomatis', 'int', '3', 'Jumlah laporan berbeda dalam 24 jam untuk blokir otomatis (0 = nonaktif)'],
        ['hari_kedaluwarsa_persetujuan', 'int', '14', 'Hari sebelum slug menunggu otomatis ditolak'],
        ['hari_pengingat_kedaluwarsa', 'int', '7', 'Hari sebelum aktif_sampai untuk pengingat'],
        ['teks_pemberitahuan_privasi', 'string', 'Alias FKIP mencatat klik secara anonim (IP dianonimkan, tanpa user agent mentah) untuk statistik layanan dan keamanan. Data kunjungan disimpan 12 bulan lalu diringkas.', 'Ringkasan untuk halaman /privasi'],
    ];

    public function run(): void
    {
        foreach (self::BAWAAN as [$kunci, $tipe, $nilai, $keterangan]) {
            Pengaturan::firstOrCreate(['kunci' => $kunci], ['tipe' => $tipe, 'nilai' => $nilai, 'keterangan' => $keterangan]);
        }
    }
}
