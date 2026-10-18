<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migrasi 2026_10_07_100000_create_tautan_pendek_table membandingkan nama driver hanya dengan 'mysql', yang TIDAK berlaku
 * untuk driver `mariadb` (Laravel 13). Migrasi baru ini menambah, bila belum ada, apa yang dilewatkan pada MariaDB:
 *   - kolom `kode` ber-collation ascii_bin (kode peka huruf besar-kecil, BR-02), dan
 *   - tiga CHECK BR-19 (kepemilikan, kode_status_redirect, jadwal).
 * Idempoten: tidak mengubah apa pun bila sudah ada. Pada SQLite (aturan sudah dijaga trigger) tidak melakukan apa-apa.
 * Migrasi lama tidak diubah.
 */
return new class extends Migration
{
    private const TABEL = 'tautan_pendek';

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->pastikanAsciiBin();
        $this->pastikanCheck();
    }

    public function down(): void
    {
        // Pengetatan sengaja tidak dibatalkan: kembali ke collation longgar akan merusak keunikan kode peka huruf.
    }

    private function pastikanAsciiBin(): void
    {
        $kolom = DB::selectOne(
            'SELECT COLLATION_NAME AS kolasi FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [self::TABEL, 'kode'],
        );

        if ($kolom !== null && $kolom->kolasi !== 'ascii_bin') {
            // Indeks unik `kode` ikut dibangun ulang oleh MODIFY.
            DB::statement('ALTER TABLE '.self::TABEL.' MODIFY kode VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
        }
    }

    private function pastikanCheck(): void
    {
        $ada = collect(DB::select(
            "SELECT CONSTRAINT_NAME AS nama FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_TYPE = 'CHECK' AND TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [self::TABEL],
        ))->pluck('nama')->all();

        $aturan = [
            'chk_tautan_kepemilikan' => "(jenis_kepemilikan = 'pribadi' AND pemilik_id IS NOT NULL AND unit_id IS NULL)
                OR (jenis_kepemilikan = 'unit' AND unit_id IS NOT NULL AND pemilik_id IS NULL)",
            'chk_tautan_kode_redirect' => 'kode_status_redirect IN (301, 302)',
            'chk_tautan_jadwal' => 'aktif_mulai IS NULL OR aktif_sampai IS NULL OR aktif_sampai > aktif_mulai',
        ];

        foreach ($aturan as $nama => $kondisi) {
            if (! in_array($nama, $ada, true)) {
                DB::statement('ALTER TABLE '.self::TABEL." ADD CONSTRAINT {$nama} CHECK ({$kondisi})");
            }
        }
    }
};
