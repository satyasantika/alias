<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungan_tautan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tautan_pendek_id')->constrained('tautan_pendek')->cascadeOnDelete();
            $table->dateTime('dikunjungi_pada');
            $table->string('ip_anonim', 45);
            $table->char('ip_hash', 64);
            $table->string('peramban', 50)->nullable();
            $table->string('versi_peramban', 10)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('versi_os', 10)->nullable();
            $table->string('jenis_perangkat', 10)->default('lainnya');
            $table->string('perujuk_host', 255)->nullable();
            $table->boolean('bot')->default(false);
            $table->string('nama_bot', 100)->nullable();

            $table->index(['tautan_pendek_id', 'dikunjungi_pada']);
            $table->index(['dikunjungi_pada', 'bot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan_tautan');
    }
};
