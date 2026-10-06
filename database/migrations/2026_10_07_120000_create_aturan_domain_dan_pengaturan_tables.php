<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aturan_domain', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pola_host', 255)->unique();
            $table->string('jenis', 10)->index();
            $table->string('alasan', 255)->nullable();
            $table->boolean('aktif')->default(true);
            $table->foreignUuid('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pengaturan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kunci', 100)->unique();
            $table->text('nilai')->nullable();
            $table->string('tipe', 10);
            $table->string('keterangan', 255)->nullable();
            $table->foreignUuid('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
        Schema::dropIfExists('aturan_domain');
    }
};
