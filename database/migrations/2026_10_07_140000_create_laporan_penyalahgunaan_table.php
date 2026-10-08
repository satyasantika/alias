<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_penyalahgunaan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tautan_pendek_id')->nullable()->constrained('tautan_pendek')->nullOnDelete();
            $table->string('kode_dilaporkan', 64);
            $table->string('kategori', 30)->index();
            $table->text('keterangan')->nullable();
            $table->string('email_pelapor')->nullable();
            $table->char('ip_hash', 64)->index();
            $table->string('status', 20)->default('baru')->index();
            $table->string('tindakan', 20)->nullable();
            $table->text('catatan_penanganan')->nullable();
            $table->foreignUuid('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ditangani_pada')->nullable();
            $table->timestamps();

            $table->index(['tautan_pendek_id', 'kategori', 'created_at'], 'laporan_penyalahgunaan_tautan_kategori_dibuat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_penyalahgunaan');
    }
};
