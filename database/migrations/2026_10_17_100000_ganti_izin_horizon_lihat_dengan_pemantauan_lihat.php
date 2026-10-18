<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): Horizon dicabut, izin `horizon.lihat` diganti `pemantauan.lihat`.
 * Migrasi DATA idempotent: menjaga pemberian izin yang sudah ada di role_has_permissions / model_has_permissions
 * (rename baris bila izin baru belum ada), atau membuang izin lama bila izin baru sudah dibuat seeder.
 * Basis data baru (tanpa izin lama) tidak terpengaruh.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ganti('horizon.lihat', 'pemantauan.lihat');
    }

    public function down(): void
    {
        $this->ganti('pemantauan.lihat', 'horizon.lihat');
    }

    private function ganti(string $lama, string $baru): void
    {
        DB::transaction(function () use ($lama, $baru): void {
            $izinLama = DB::table('permissions')->where('guard_name', 'web')->where('name', $lama)->lockForUpdate()->first();

            if ($izinLama === null) {
                return;
            }

            $izinBaru = DB::table('permissions')->where('guard_name', 'web')->where('name', $baru)->lockForUpdate()->first();

            if ($izinBaru === null) {
                DB::table('permissions')->where('id', $izinLama->id)->update(['name' => $baru, 'updated_at' => now()]);

                return;
            }

            // Kedua izin ada: pindahkan pemberian ke izin baru lalu hapus izin lama beserta pivotnya.
            $kolomModel = config('permission.column_names.model_morph_key', 'model_id'); // 'model_uuid' di proyek ini

            foreach (['role_has_permissions' => 'role_id', 'model_has_permissions' => $kolomModel] as $tabel => $kolom) {
                $sudahAda = DB::table($tabel)->where('permission_id', $izinBaru->id)->pluck($kolom)->all();

                $kueri = DB::table($tabel)->where('permission_id', $izinLama->id);
                $tabel === 'model_has_permissions' ? $kueri->select([$kolom, 'model_type']) : $kueri->select([$kolom]);

                foreach ($kueri->get() as $baris) {
                    if (in_array($baris->{$kolom}, $sudahAda, true)) {
                        continue;
                    }

                    DB::table($tabel)->insert((array) $baris + ['permission_id' => $izinBaru->id]);
                }
            }

            DB::table('model_has_permissions')->where('permission_id', $izinLama->id)->delete();
            DB::table('role_has_permissions')->where('permission_id', $izinLama->id)->delete();
            DB::table('permissions')->where('id', $izinLama->id)->delete();
        });

        // Cache izin spatie (store `database` saat produksi) memuat nama lama.
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
            // tabel cache belum ada pada instalasi baru: tidak ada yang perlu dibersihkan
        }
    }
};
