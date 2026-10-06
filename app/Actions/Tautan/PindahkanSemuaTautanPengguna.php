<?php

namespace App\Actions\Tautan;

use App\Enums\Izin;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** BR-30: pindahkan semua tautan pribadi pengguna (yang pindah/purnatugas) ke unit atau pengguna lain. */
class PindahkanSemuaTautanPengguna
{
    public const KERAS = 200;

    public function jalankan(User $dari, User|Unit $ke, User $oleh, string $alasan = 'Pegawai pindah tugas'): int
    {
        Gate::forUser($oleh)->authorize(Izin::TautanTransfer->value);
        abort_unless($oleh->adalahAdmin(), 403);

        if ($ke instanceof User && $ke->is($dari)) {
            throw ValidationException::withMessages(['ke' => 'Tujuan pemindahan sama dengan pemilik saat ini.']);
        }

        $kunci = Cache::lock('pindah-tautan-massal:'.$dari->getKey(), 120);

        if (! $kunci->get()) {
            throw ValidationException::withMessages(['ke' => 'Pemindahan massal untuk pengguna ini sedang berjalan.']);
        }

        try {
            $jumlah = 0;
            $aksi = app(PindahkanKepemilikanTautan::class);

            TautanPendek::query()
                ->where('jenis_kepemilikan', 'pribadi')
                ->where('pemilik_id', $dari->getKey())
                ->orderBy('id')
                ->chunkById(self::KERAS, function ($kelompok) use ($aksi, $ke, $oleh, $alasan, &$jumlah): void {
                    DB::transaction(function () use ($kelompok, $aksi, $ke, $oleh, $alasan, &$jumlah): void {
                        foreach ($kelompok as $tautan) {
                            $aksi->terapkan($tautan, $ke, $oleh, $alasan, periksaArah: false, periksaKuota: false);
                            $jumlah++;
                        }
                    });
                });

            return $jumlah;
        } finally {
            $kunci->release();
        }
    }
}
