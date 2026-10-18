<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b, §4a butir 6): antrean memakai driver `database`. Driver ini dan penyedia
 * `failed_jobs` (database-uuids) MENYISIPKAN baris TANPA kolom `id`, sehingga `id` wajib auto-increment (kunci
 * bawaan Laravel). Migrasi awal (0001_01_01_000002) membuat `jobs.id` dan `failed_jobs.id` bertipe UUID tanpa
 * default: setiap dispatch ke antrean database akan gagal ("Field 'id' doesn't have a default value").
 * Migrasi lama tidak diubah; migrasi ini mengganti kunci kedua tabel, idempotent (tidak mengubah bila sudah integer).
 * Baris yang ada dipertahankan (id baru diberikan berurutan).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['jobs', 'failed_jobs'] as $tabel) {
            if (! Schema::hasTable($tabel) || $this->idSudahInteger($tabel)) {
                continue;
            }

            in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
                ? $this->ubahDiMariadb($tabel)
                : $this->bangunUlang($tabel);
        }
    }

    public function down(): void
    {
        // Tidak dibatalkan: kunci UUID membuat antrean database tidak dapat dipakai.
    }

    private function idSudahInteger(string $tabel): bool
    {
        foreach (Schema::getColumns($tabel) as $kolom) {
            if ($kolom['name'] === 'id') {
                return in_array(strtolower((string) $kolom['type_name']), ['bigint', 'int', 'integer'], true);
            }
        }

        return false;
    }

    private function ubahDiMariadb(string $tabel): void
    {
        DB::statement("ALTER TABLE `{$tabel}` DROP PRIMARY KEY, DROP COLUMN `id`, ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`)");
    }

    /** Driver lain (SQLite warisan): tabel dibangun ulang dengan baris disalin tanpa id lama. */
    private function bangunUlang(string $tabel): void
    {
        $baris = DB::table($tabel)->get()->map(fn ($b) => collect((array) $b)->except('id')->all())->all();

        Schema::drop($tabel);

        Schema::create($tabel, function (Blueprint $table) use ($tabel): void {
            $table->id();

            if ($tabel === 'jobs') {
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedSmallInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');

                return;
            }

            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
            $table->index(['connection', 'queue', 'failed_at']);
        });

        foreach (array_chunk($baris, 200) as $kelompok) {
            DB::table($tabel)->insert($kelompok);
        }
    }
};
