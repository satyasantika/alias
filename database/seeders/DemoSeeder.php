<?php

namespace Database\Seeders;

use App\Jobs\PindaiUlangAturan;
use App\Models\KunjunganTautan;
use App\Models\LaporanPenyalahgunaan;
use App\Models\PermintaanAkses;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\SlugKustomMenunggu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 03-SKEMA §6.7 — data demo (local saja): akun & tautan UAT + kunjungan sintetis 30 hari, laporan, permintaan akses.
 * Dipakai untuk tangkapan layar panduan. IP memakai blok dokumentasi 192.0.2.0.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder hanya untuk lingkungan local.');
        }

        Artisan::call('alias:siapkan-uat');

        PindaiUlangAturan::tanpaPindai(function (): void {
            $dosen = User::where('email', 'dosen.a@unsil.ac.id')->firstOrFail();
            $pmat = Unit::where('kode', 'PMAT')->firstOrFail();

            $this->tautanTambahan($dosen, $pmat);
            $this->kunjungan();
            $this->laporanDanPermintaan();
        });

        DB::statement('update tautan_pendek set jumlah_klik = (select count(*) from kunjungan_tautan k where k.tautan_pendek_id = tautan_pendek.id and k.bot = 0)');

        Artisan::call('alias:rekap-kunjungan', ['--tanggal' => now()->subDay()->format('Y-m-d')]);
        for ($i = 2; $i <= 30; $i++) {
            Artisan::call('alias:rekap-kunjungan', ['--tanggal' => now()->subDays($i)->format('Y-m-d')]);
        }

        $admin = User::where('email', 'admin.alias@unsil.ac.id')->first();
        if ($admin !== null) {
            $slug = TautanPendek::where('kode', 'seminar-pmat-2026')->first();
            $admin->notify(new SlugKustomMenunggu($slug));
        }
    }

    private function tautanTambahan(User $dosen, Unit $pmat): void
    {
        $judul = ['Pendaftaran PPL 2026', 'Jadwal ujian akhir', 'Materi kuliah Kalkulus', 'Rapat gugus mutu', 'Formulir beasiswa', 'Presensi seminar', 'Panduan skripsi', 'Pengumuman yudisium'];
        foreach ($judul as $i => $j) {
            $url = 'https://forms.gle/demo'.($i + 1);
            TautanPendek::query()->firstOrCreate(['kode' => 'Demo00'.($i + 1)], [
                'kode_kustom' => false, 'judul' => $j, 'url_tujuan' => $url, 'host_tujuan' => 'forms.gle', 'url_tujuan_hash' => hash('sha256', $url),
                'jenis_kepemilikan' => 'pribadi', 'pemilik_id' => $dosen->getKey(), 'dibuat_oleh' => $dosen->getKey(),
            ])->forceFill(['status' => 'aktif', 'pertama_aktif_pada' => now()->subDays(40), 'status_cek_tujuan' => $i % 5 === 4 ? 'bermasalah' : 'sehat'])->saveQuietly();
        }
    }

    private function kunjungan(): void
    {
        if (KunjunganTautan::query()->exists()) {
            return;
        }

        $tautan = TautanPendek::query()->where('status', 'aktif')->pluck('id')->all();
        $perangkat = ['desktop', 'ponsel', 'ponsel', 'tablet'];
        $peramban = ['Chrome', 'Chrome', 'Firefox', 'Safari', 'Edge'];
        $perujuk = [null, null, 'wa.me', 'www.facebook.com', 't.me', 'unsil.ac.id'];
        mt_srand(42);

        for ($hari = 30; $hari >= 0; $hari--) {
            foreach ($tautan as $n => $id) {
                $banyak = mt_rand(0, 3 + (($n * 7 + $hari) % 9));
                $baris = [];
                for ($i = 0; $i < $banyak; $i++) {
                    $bot = mt_rand(1, 12) === 1;
                    $baris[] = [
                        'id' => (string) Str::uuid7(), 'tautan_pendek_id' => $id,
                        'dikunjungi_pada' => now()->subDays($hari)->setTime(mt_rand(6, 22), mt_rand(0, 59), mt_rand(0, 59))->format('Y-m-d H:i:s'),
                        'ip_anonim' => '192.0.2.0', 'ip_hash' => hash('sha256', $hari.'-'.mt_rand(1, 40)),
                        'peramban' => $peramban[array_rand($peramban)], 'versi_peramban' => '124', 'os' => ['Windows', 'Android', 'iOS'][mt_rand(0, 2)],
                        'jenis_perangkat' => $bot ? 'bot' : $perangkat[array_rand($perangkat)], 'perujuk_host' => $perujuk[array_rand($perujuk)], 'bot' => $bot,
                    ];
                }
                if ($baris !== []) {
                    KunjunganTautan::query()->insert($baris);
                }
            }
        }
    }

    private function laporanDanPermintaan(): void
    {
        $diblokir = TautanPendek::where('kode', 'uat-contoh')->first();
        foreach ([['judi', 'Mengarah ke situs judi daring.'], ['phishing', 'Meniru halaman login SIAKAD.']] as $i => [$kategori, $ket]) {
            LaporanPenyalahgunaan::query()->firstOrCreate(['kode_dilaporkan' => 'uat-contoh', 'kategori' => $kategori], [
                'tautan_pendek_id' => $diblokir?->getKey(), 'keterangan' => $ket, 'ip_hash' => hash('sha256', 'pelapor'.$i),
            ]);
        }

        foreach ([['Calon Dosen Baru', 'calon.dosen@unsil.ac.id', 'Membuat tautan pendaftaran seminar prodi.'], ['Pengurus HIMA', 'hima.pmat@unsil.ac.id', 'Membagikan tautan kegiatan himpunan.']] as [$nama, $surel, $alasan]) {
            PermintaanAkses::query()->firstOrCreate(['email' => $surel], [
                'nama' => $nama, 'alasan' => $alasan, 'ip_hash' => hash('sha256', $surel), 'status' => 'menunggu', 'email_terverifikasi_pada' => now(),
                'unit_id' => Unit::where('kode', 'PMAT')->value('id'),
            ]);
        }
    }
}
