<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AturKataSandiAkun;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Migrasi Profil Ringan: (1) migrasi DATA izin horizon.lihat -> pemantauan.lihat, (2) pengetatan MariaDB
 * (ascii_bin + CHECK) pada tautan_pendek. Keduanya idempotent.
 */
function jalankanMigrasi(string $kataKunci): void
{
    $berkas = glob(database_path("migrations/*{$kataKunci}*.php"));
    expect($berkas)->toHaveCount(1);

    (require $berkas[0])->up();
}

it('mengganti nama izin horizon.lihat menjadi pemantauan.lihat dengan menjaga pemberian ke peran', function () {
    $lama = Permission::create(['name' => 'horizon.lihat', 'guard_name' => 'web']);
    $peran = Role::create(['name' => 'super-admin-uji', 'guard_name' => 'web']);
    $peran->givePermissionTo($lama);

    jalankanMigrasi('ganti_izin_horizon_lihat');
    jalankanMigrasi('ganti_izin_horizon_lihat'); // idempotent

    expect(Permission::where('name', 'horizon.lihat')->exists())->toBeFalse()
        ->and(Permission::where('name', 'pemantauan.lihat')->count())->toBe(1)
        ->and(Permission::where('name', 'pemantauan.lihat')->value('id'))->toBe($lama->id)
        ->and(Role::findByName('super-admin-uji')->hasPermissionTo('pemantauan.lihat'))->toBeTrue();
});

it('menggabungkan izin lama ke izin baru bila keduanya sudah ada (seeder sudah berjalan)', function () {
    $lama = Permission::create(['name' => 'horizon.lihat', 'guard_name' => 'web']);
    $baru = Permission::create(['name' => 'pemantauan.lihat', 'guard_name' => 'web']);
    $peranA = Role::create(['name' => 'peran-a', 'guard_name' => 'web']);
    $peranB = Role::create(['name' => 'peran-b', 'guard_name' => 'web']);
    $peranA->givePermissionTo($lama);
    $peranB->givePermissionTo($lama, $baru);
    $pengguna = User::factory()->create();
    $pengguna->givePermissionTo($lama);

    jalankanMigrasi('ganti_izin_horizon_lihat');

    expect(Permission::where('name', 'horizon.lihat')->exists())->toBeFalse()
        ->and(Permission::where('name', 'pemantauan.lihat')->count())->toBe(1)
        ->and(Role::findByName('peran-a')->hasPermissionTo('pemantauan.lihat'))->toBeTrue()
        ->and(Role::findByName('peran-b')->hasPermissionTo('pemantauan.lihat'))->toBeTrue()
        ->and($pengguna->fresh()->hasPermissionTo('pemantauan.lihat'))->toBeTrue()
        ->and(DB::table('role_has_permissions')->where('permission_id', $baru->id)->count())->toBe(2);
});

it('tidak melakukan apa pun pada basis data tanpa izin lama', function () {
    $sebelum = Permission::count();

    jalankanMigrasi('ganti_izin_horizon_lihat');

    expect(Permission::count())->toBe($sebelum);
});

it('memberi jobs dan failed_jobs kunci auto-increment sehingga antrean database dapat dipakai', function () {
    jalankanMigrasi('perbaiki_kunci_tabel_jobs_dan_failed_jobs'); // idempotent: sudah dijalankan oleh RefreshDatabase

    // Pola driver antrean database: sisipan TANPA kolom id.
    foreach (range(1, 2) as $_) {
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()]);
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'uji']);
    }

    // id auto-increment TIDAK ikut rollback RefreshDatabase antar test (beda dengan baris data), jadi nilai
    // mutlaknya tidak bisa ditebak di suite penuh; yang dibuktikan di sini cuma sisipan tanpa kolom id berhasil
    // dan kedua id yang dihasilkan berurutan.
    $id = DB::table('jobs')->orderBy('id')->pluck('id')->map(fn ($i) => (int) $i)->all();
    expect($id)->toHaveCount(2)->and($id[1])->toBe($id[0] + 1)
        ->and(DB::table('failed_jobs')->count())->toBe(2);

    // Job sungguhan lewat koneksi database (bukan sync).
    Notification::route('mail', 'a@unsil.ac.id')->notify((new AturKataSandiAkun('token'))->onConnection('database'));

    expect(DB::table('jobs')->count())->toBe(3);
});

describe('pengetatan MariaDB tautan_pendek', function () {
    beforeEach(function () {
        if (! in_array(DB::getDriverName(), ['mariadb', 'mysql'], true)) {
            $this->markTestSkipped('Membutuhkan MariaDB/MySQL.');
        }
    });

    it('memakai kolasi ascii_bin pada kode dan idempotent saat dijalankan ulang', function () {
        jalankanMigrasi('pastikan_ascii_bin_dan_check_tautan_pendek');
        jalankanMigrasi('pastikan_ascii_bin_dan_check_tautan_pendek');

        $kolasi = DB::selectOne("SELECT COLLATION_NAME AS k FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tautan_pendek' AND COLUMN_NAME = 'kode'")->k;

        expect($kolasi)->toBe('ascii_bin');

        $pemilik = User::factory()->create();
        TautanPendek::factory()->milikPribadi($pemilik)->create(['kode' => 'AbCd123']);
        TautanPendek::factory()->milikPribadi($pemilik)->create(['kode' => 'abcd123']); // peka huruf besar-kecil (BR-02)

        expect(TautanPendek::count())->toBe(2);
    });

    it('memasang tiga CHECK BR-19 tepat satu kali', function () {
        jalankanMigrasi('pastikan_ascii_bin_dan_check_tautan_pendek');

        $nama = collect(DB::select("SELECT CONSTRAINT_NAME AS n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_TYPE = 'CHECK' AND TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tautan_pendek'"))->pluck('n');

        expect($nama->sort()->values()->all())->toBe(['chk_tautan_jadwal', 'chk_tautan_kepemilikan', 'chk_tautan_kode_redirect']);
    });

    it('menolak baris yang melanggar CHECK kepemilikan, kode redirect, dan jadwal', function () {
        $pemilik = User::factory()->create();
        $unit = Unit::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'jenis' => 'prodi']);

        $pelanggaran = [
            ['pemilik_id' => $pemilik->id, 'unit_id' => $unit->id],               // dua pemilik sekaligus
            ['kode_status_redirect' => 303],
            ['aktif_mulai' => now()->addDay(), 'aktif_sampai' => now()],          // sampai <= mulai
        ];

        foreach ($pelanggaran as $atribut) {
            expect(fn () => TautanPendek::factory()->milikPribadi($pemilik)->create($atribut))->toThrow(QueryException::class);
        }
    });
});
