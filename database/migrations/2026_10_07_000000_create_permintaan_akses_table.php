<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_akses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 150);
            $table->string('email')->index();
            $table->string('nip', 30)->nullable();
            $table->foreignUuid('unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->string('peran_diminta', 20)->default('pengguna');
            $table->string('alasan', 1000);
            $table->string('sumber', 20)->default('formulir');
            $table->foreignUuid('diusulkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('menunggu_verifikasi_surel')->index();
            $table->dateTime('email_terverifikasi_pada')->nullable();
            $table->foreignUuid('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diproses_pada')->nullable();
            $table->string('catatan', 1000)->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('ip_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_akses');
    }
};
