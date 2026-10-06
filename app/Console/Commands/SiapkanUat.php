<?php

namespace App\Console\Commands;

use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Enums\StatusTautan;
use App\Jobs\PindaiUlangAturan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\SlugTerlarangSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

/** 05-UJI-PENERIMAAN §1: data untuk UAT di staging. Idempoten. Menolak berjalan di produksi. */
#[AsCommand(name: 'alias:siapkan-uat', description: 'Siapkan data UAT (peran, unit contoh, akun uji, tautan contoh) di staging/lokal')]
class SiapkanUat extends Command
{
    public function handle(): int
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            $this->error('alias:siapkan-uat hanya untuk lingkungan local/staging.');

            return self::FAILURE;
        }

        $kataSandi = config('alias.seed_password');
        if (blank($kataSandi)) {
            $this->error('SEED_PASSWORD wajib diisi.');

            return self::FAILURE;
        }

        PindaiUlangAturan::tanpaPindai(function (): void {
            foreach ([PeranDanIzinSeeder::class, UnitSeeder::class, SlugTerlarangSeeder::class, AturanDomainSeeder::class, PengaturanSeeder::class] as $seeder) {
                $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
            }
        });

        $pmat = Unit::where('kode', 'PMAT')->firstOrFail();
        $kedua = Unit::updateOrCreate(['kode' => 'PBIO'], ['nama' => 'Pendidikan Biologi', 'jenis' => 'prodi', 'induk_id' => $pmat->induk_id, 'prefiks_slug' => 'pbio']);

        $akun = [
            'superadmin@unsil.ac.id' => ['Super Admin UAT', Peran::SuperAdmin, null, null],
            'admin.alias@unsil.ac.id' => ['Admin Alias UAT', Peran::AdminAlias, null, null],
            'pengelola.pmat@unsil.ac.id' => ['Pengelola PMAT', Peran::PengelolaUnit, $pmat, PeranUnit::Pengelola],
            'dosen.a@unsil.ac.id' => ['Dosen A (PMAT)', Peran::Pengguna, $pmat, PeranUnit::Anggota],
            'dosen.b@unsil.ac.id' => ['Dosen B (PBIO)', Peran::Pengguna, $kedua, PeranUnit::Anggota],
            'wakil.dekan@unsil.ac.id' => ['Wakil Dekan', Peran::Pemantau, null, null],
        ];

        $pengguna = [];
        foreach ($akun as $surel => [$nama, $peran, $unit, $peranUnit]) {
            $user = User::query()->where('email', $surel)->first() ?? new User;
            $user->forceFill([
                'name' => $nama, 'email' => $surel, 'password' => Hash::make($kataSandi), 'email_verified_at' => now(),
                'aktif' => true, 'terkunci_sampai' => null,
            ])->save();
            $user->syncRoles([$peran->value]);

            if ($unit !== null) {
                $user->unitAnggota()->syncWithoutDetaching([$unit->getKey() => ['id' => (string) Str::uuid7(), 'peran_unit' => $peranUnit->value]]);
            }

            if ($peran->wajibMfaPeran() && $user->getAppAuthenticationSecret() === null) {
                $rahasia = AppAuthentication::make()->generateSecret();
                $user->saveAppAuthenticationSecret($rahasia);
                $this->warn("MFA {$surel}: rahasia TOTP = {$rahasia}");
            }

            $pengguna[$surel] = $user;
        }

        $this->tautanContoh($pengguna['dosen.a@unsil.ac.id'], $pengguna['dosen.b@unsil.ac.id'], $pengguna['pengelola.pmat@unsil.ac.id'], $pmat);
        RateLimiter::clear('uat');

        $this->table(['Peran', 'Surel'], collect($akun)->map(fn ($a, $s) => [$a[1]->value, $s])->values()->all());
        $this->info('Data UAT siap. Kata sandi semua akun = nilai SEED_PASSWORD.');

        return self::SUCCESS;
    }

    private function tautanContoh(User $dosenA, User $dosenB, User $pengelola, Unit $pmat): void
    {
        $now = now();
        $buat = function (string $kode, string $judul, string $url, array $atribut = [], ?string $status = null) use ($dosenA) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $t = TautanPendek::query()->withTrashed()->where('kode', $kode)->first() ?? new TautanPendek;
            $t->fill(array_merge([
                'kode' => $kode, 'kode_kustom' => ! preg_match('/^[A-Za-z0-9]{7}$/', $kode), 'judul' => $judul, 'url_tujuan' => $url,
                'host_tujuan' => $host, 'url_tujuan_hash' => hash('sha256', $url),
                'jenis_kepemilikan' => 'pribadi', 'pemilik_id' => $dosenA->getKey(), 'unit_id' => null, 'dibuat_oleh' => $dosenA->getKey(),
            ], $atribut));
            $t->forceFill(['status' => $status ?? 'aktif', 'pertama_aktif_pada' => ($status ?? 'aktif') === 'menunggu_persetujuan' || $status === 'ditolak' ? null : now()])->save();

            return $t;
        };

        $buat('UatAktif', 'Formulir seminar (aktif)', 'https://forms.gle/uat-aktif');
        $buat('seminar-pmat-2026', 'Seminar PMAT 2026 (menunggu persetujuan)', 'https://forms.gle/uat-seminar', [], StatusTautan::MenungguPersetujuan->value);
        $buat('uat-ditolak', 'Slug ditolak', 'https://forms.gle/uat-ditolak', [], StatusTautan::Ditolak->value);
        $buat('uat-nonaktif', 'Dinonaktifkan pemilik', 'https://forms.gle/uat-nonaktif', [], StatusTautan::Dinonaktifkan->value);
        $buat('uat-diblokir', 'Diblokir moderator', 'https://forms.gle/uat-diblokir', [], StatusTautan::Diblokir->value);
        $buat('uat-besok', 'Terjadwal mulai besok', 'https://forms.gle/uat-besok', ['aktif_mulai' => $now->copy()->addDay()]);
        $buat('uat-lewat', 'Sudah kedaluwarsa', 'https://forms.gle/uat-lewat', ['aktif_sampai' => $now->copy()->subDay()]);
        $buat('uat-sekali', 'Sekali pakai (undangan)', 'https://forms.gle/uat-sekali', ['sekali_pakai' => true]);
        $buat('uat-batas3', 'Batas 3 klik', 'https://forms.gle/uat-batas', ['batas_klik' => 3]);
        $sandi = $buat('uat-sandi', 'Berkata sandi (uji-sandi)', 'https://forms.gle/uat-sandi');
        $sandi->forceFill(['kata_sandi_hash' => Hash::make('uji-sandi')])->save();
        $buat('uat-contoh', 'Tautan ke contoh.com (uji aturan domain A-06)', 'https://contoh.com/halaman');
        $buat('UatUnit1', 'Tautan milik unit PMAT', 'https://forms.gle/uat-unit', [
            'jenis_kepemilikan' => 'unit', 'pemilik_id' => null, 'unit_id' => $pmat->getKey(), 'dibuat_oleh' => $pengelola->getKey(),
        ]);
        $buat('UatDosnB', 'Tautan milik dosen.b (uji IDOR P-08)', 'https://forms.gle/uat-dosenb', [
            'pemilik_id' => $dosenB->getKey(), 'dibuat_oleh' => $dosenB->getKey(),
        ]);
    }
}
