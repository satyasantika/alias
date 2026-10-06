<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        Schema::create('tautan_pendek', function (Blueprint $table) use ($mysql) {
            $table->uuid('id')->primary();

            // MySQL: ascii_bin (peka huruf besar-kecil). SQLite: perbandingan default sudah biner.
            $kode = $table->string('kode', 64);
            if ($mysql) {
                $kode->charset('ascii')->collation('ascii_bin');
            }
            $kode->unique();

            $table->boolean('kode_kustom')->default(false);
            $table->string('judul', 150);
            $table->string('keterangan', 500)->nullable();
            $table->string('url_tujuan', 2048);
            $table->string('host_tujuan', 255)->index();
            $table->char('url_tujuan_hash', 64)->index();
            $table->string('jenis_kepemilikan', 10)->default('pribadi');
            $table->foreignUuid('pemilik_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('unit_id')->nullable()->index()->constrained('unit')->restrictOnDelete();
            $table->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('aktif')->index();
            $table->string('alasan_status', 500)->nullable();
            $table->dateTime('pertama_aktif_pada')->nullable();
            $table->foreignUuid('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disetujui_pada')->nullable();
            $table->dateTime('aktif_mulai')->nullable();
            $table->dateTime('aktif_sampai')->nullable()->index();
            $table->unsignedInteger('batas_klik')->nullable();
            $table->boolean('sekali_pakai')->default(false);
            $table->dateTime('dipakai_pada')->nullable();
            $table->boolean('teruskan_query')->default(false);
            $table->boolean('catat_kunjungan')->default(true);
            $table->unsignedSmallInteger('kode_status_redirect')->default(302);
            $table->string('kata_sandi_hash')->nullable();
            $table->unsignedBigInteger('jumlah_klik')->default(0);
            $table->dateTime('klik_terakhir_pada')->nullable();
            $table->string('status_cek_tujuan', 20)->default('belum');
            $table->unsignedSmallInteger('kode_http_terakhir')->nullable();
            $table->dateTime('dicek_tujuan_pada')->nullable();
            $table->unsignedTinyInteger('gagal_cek_beruntun')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->foreignUuid('dihapus_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['pemilik_id', 'deleted_at', 'status']);
            $table->index(['unit_id', 'deleted_at', 'status']);
            $table->index(['status', 'status_cek_tujuan']);
            $table->index(['status', 'created_at']);
        });

        $this->pasangConstraint($mysql);

        Schema::create('riwayat_status_tautan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tautan_pendek_id')->constrained('tautan_pendek')->cascadeOnDelete();
            $table->string('dari_status', 30)->nullable();
            $table->string('ke_status', 30);
            $table->string('alasan', 500)->nullable();
            $table->foreignUuid('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('riwayat_kepemilikan_tautan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tautan_pendek_id')->constrained('tautan_pendek')->cascadeOnDelete();
            $table->string('dari_jenis', 10);
            $table->foreignUuid('dari_pemilik_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('dari_unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->string('ke_jenis', 10);
            $table->foreignUuid('ke_pemilik_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('ke_unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->string('alasan', 500)->nullable();
            $table->foreignUuid('oleh')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kepemilikan_tautan');
        Schema::dropIfExists('riwayat_status_tautan');
        Schema::dropIfExists('tautan_pendek');
    }

    /** BR-19: CHECK di MySQL; SQLite tidak mendukung ADD CONSTRAINT sehingga memakai trigger setara. */
    private function pasangConstraint(bool $mysql): void
    {
        $kepemilikan = "(jenis_kepemilikan = 'pribadi' AND pemilik_id IS NOT NULL AND unit_id IS NULL)
            OR (jenis_kepemilikan = 'unit' AND unit_id IS NOT NULL AND pemilik_id IS NULL)";
        $redirect = 'kode_status_redirect IN (301, 302)';
        $jadwal = 'aktif_mulai IS NULL OR aktif_sampai IS NULL OR aktif_sampai > aktif_mulai';

        if ($mysql) {
            DB::statement("ALTER TABLE tautan_pendek
                ADD CONSTRAINT chk_tautan_kepemilikan CHECK ({$kepemilikan}),
                ADD CONSTRAINT chk_tautan_kode_redirect CHECK ({$redirect}),
                ADD CONSTRAINT chk_tautan_jadwal CHECK ({$jadwal})");

            return;
        }

        $aturan = ['chk_tautan_kepemilikan' => $kepemilikan, 'chk_tautan_kode_redirect' => $redirect, 'chk_tautan_jadwal' => $jadwal];
        foreach ($aturan as $nama => $kondisi) {
            $kondisiBaru = preg_replace('/\b(jenis_kepemilikan|pemilik_id|unit_id|kode_status_redirect|aktif_mulai|aktif_sampai)\b/', 'NEW.$1', $kondisi);
            foreach (['INSERT' => 'ins', 'UPDATE' => 'upd'] as $peristiwa => $akhiran) {
                DB::statement("CREATE TRIGGER {$nama}_{$akhiran} BEFORE {$peristiwa} ON tautan_pendek
                    WHEN NOT ({$kondisiBaru})
                    BEGIN SELECT RAISE(ABORT, 'CHECK constraint failed: {$nama}'); END");
            }
        }
    }
};
