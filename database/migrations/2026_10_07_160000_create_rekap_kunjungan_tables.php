<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_kunjungan_harian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tautan_pendek_id')->constrained('tautan_pendek')->cascadeOnDelete();
            $table->date('tanggal');
            $table->unsignedInteger('jumlah_klik')->default(0);
            $table->unsignedInteger('jumlah_pengunjung_unik')->default(0);
            $table->unsignedInteger('jumlah_bot')->default(0);
            $table->timestamps();

            $table->unique(['tautan_pendek_id', 'tanggal']);
            $table->index('tanggal');
        });

        Schema::create('rekap_kunjungan_dimensi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tautan_pendek_id')->constrained('tautan_pendek')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('dimensi', 20);
            $table->string('nilai', 255);
            $table->unsignedInteger('jumlah')->default(0);

            $table->unique(['tautan_pendek_id', 'tanggal', 'dimensi', 'nilai'], 'rekap_dimensi_unik');
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_kunjungan_dimensi');
        Schema::dropIfExists('rekap_kunjungan_harian');
    }
};
