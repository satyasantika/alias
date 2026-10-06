<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Akun contoh (bukan akun nyata). Hanya untuk lingkungan local/staging; kata sandi dari SEED_PASSWORD. */
class PenggunaAwalSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: Peran}> */
    public const AKUN = [
        'superadmin@unsil.ac.id' => ['Super Admin Alias', Peran::SuperAdmin],
        'admin.alias@unsil.ac.id' => ['Admin Alias', Peran::AdminAlias],
        'pengelola.pmat@unsil.ac.id' => ['Pengelola PMAT', Peran::PengelolaUnit],
        'pengelola.pbio@unsil.ac.id' => ['Pengelola PBIO', Peran::PengelolaUnit],
        'dosen.a@unsil.ac.id' => ['Dosen A', Peran::Pengguna],
        'dosen.b@unsil.ac.id' => ['Dosen B', Peran::Pengguna],
        'dekan@unsil.ac.id' => ['Dekan', Peran::Pemantau],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            $this->command?->warn('PenggunaAwalSeeder dilewati: hanya untuk local/staging.');

            return;
        }

        $kataSandi = config('alias.seed_password');
        if (blank($kataSandi)) {
            throw new RuntimeException('SEED_PASSWORD wajib diisi untuk membuat pengguna awal.');
        }

        foreach (self::AKUN as $surel => [$nama, $peran]) {
            $user = User::firstOrCreate(['email' => $surel], [
                'name' => $nama,
                'password' => $kataSandi,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$peran->value]);
        }
    }
}
