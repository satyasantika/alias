<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->string('jenis', 20)->index();
            $table->foreignUuid('induk_id')->nullable()->constrained('unit')->restrictOnDelete();
            $table->string('prefiks_slug', 20)->nullable()->unique();
            $table->unsignedInteger('kuota_tautan')->nullable();
            $table->boolean('aktif')->default(true)->index();
            $table->string('kode_eksternal', 50)->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('anggota_unit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unit_id')->constrained('unit')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('peran_unit', 20)->default('anggota')->index();
            $table->foreignUuid('ditambahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'user_id']);
        });

        // SQLite tidak dapat menambah FK pada tabel yang sudah ada; di MySQL/produksi FK ditambahkan.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('unit_id')->references('id')->on('unit')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['unit_id']));
        }
        Schema::dropIfExists('anggota_unit');
        Schema::dropIfExists('unit');
    }
};
