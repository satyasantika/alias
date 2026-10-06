<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('nip', 30)->nullable()->index()->after('password');
            $table->string('nidn', 20)->nullable()->index()->after('nip');
            // FK ke tabel unit ditambahkan di F3.1.
            $table->uuid('unit_id')->nullable()->index()->after('nidn');
            $table->boolean('aktif')->default(true)->index()->after('unit_id');
            $table->unsignedInteger('kuota_tautan')->nullable()->after('aktif');
            $table->dateTime('terkunci_sampai')->nullable()->after('kuota_tautan');
            $table->dateTime('terakhir_masuk_pada')->nullable()->after('terkunci_sampai');
            $table->string('metode_login_terakhir', 20)->nullable()->after('terakhir_masuk_pada');
            $table->string('google_id', 64)->nullable()->unique()->after('metode_login_terakhir');
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->string('kode_eksternal', 50)->nullable()->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'nip', 'nidn', 'unit_id', 'aktif', 'kuota_tautan', 'terkunci_sampai', 'terakhir_masuk_pada',
                'metode_login_terakhir', 'google_id', 'app_authentication_secret',
                'app_authentication_recovery_codes', 'kode_eksternal',
            ]);
        });
    }
};
