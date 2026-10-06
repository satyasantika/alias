<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_terlarang', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pola', 64);
            $table->string('jenis', 30)->index();
            $table->string('cara_cocok', 10)->default('persis');
            $table->string('keterangan', 255)->nullable();
            $table->boolean('aktif')->default(true);
            $table->foreignUuid('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pola', 'cara_cocok']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_terlarang');
    }
};
