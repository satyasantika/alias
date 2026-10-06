<?php

namespace App\Actions\Moderasi;

use App\Actions\Tautan\BlokirTautan;
use App\Enums\Izin;
use App\Enums\StatusLaporan;
use App\Enums\StatusTautan;
use App\Models\LaporanPenyalahgunaan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Diagram PRD §8.4: baru → ditinjau → ditindaklanjuti | ditolak. */
class TanganiLaporan
{
    public function tinjau(LaporanPenyalahgunaan $laporan, User $oleh): LaporanPenyalahgunaan
    {
        $this->otorisasi($oleh);

        if ($laporan->status !== StatusLaporan::Baru) {
            throw ValidationException::withMessages(['status' => 'Hanya laporan baru yang dapat diambil untuk ditinjau.']);
        }

        $laporan->update(['status' => StatusLaporan::Ditinjau, 'ditangani_oleh' => $oleh->getKey()]);
        activity('moderasi')->performedOn($laporan)->causedBy($oleh)->event('ditinjau')->log('Laporan diambil untuk ditinjau');

        return $laporan;
    }

    /** Memblokir tautan; semua laporan terbuka untuk tautan itu ikut ditindaklanjuti. */
    public function blokirTautan(LaporanPenyalahgunaan $laporan, string $alasan, User $oleh): LaporanPenyalahgunaan
    {
        $this->otorisasi($oleh);
        $this->pastikanTerbuka($laporan);

        $tautan = $laporan->tautan;
        if ($tautan === null) {
            throw ValidationException::withMessages(['tautan' => 'Laporan ini tidak terkait tautan yang ditemukan.']);
        }

        return DB::transaction(function () use ($laporan, $tautan, $alasan, $oleh): LaporanPenyalahgunaan {
            if ($tautan->status !== StatusTautan::Diblokir) {
                app(BlokirTautan::class)->jalankan($tautan, $alasan, $oleh);
            }

            LaporanPenyalahgunaan::query()
                ->where('tautan_pendek_id', $tautan->getKey())
                ->whereIn('status', [StatusLaporan::Baru, StatusLaporan::Ditinjau])
                ->get()
                ->each(fn (LaporanPenyalahgunaan $l) => $this->selesaikan($l, StatusLaporan::Ditindaklanjuti, 'blokir', $alasan, $oleh));

            return $laporan->refresh();
        });
    }

    public function tolak(LaporanPenyalahgunaan $laporan, string $alasan, User $oleh): LaporanPenyalahgunaan
    {
        $this->otorisasi($oleh);
        $this->pastikanTerbuka($laporan);

        if (mb_strlen(trim($alasan)) < 5) {
            throw ValidationException::withMessages(['alasan' => 'Alasan penolakan wajib diisi.']);
        }

        $this->selesaikan($laporan, StatusLaporan::Ditolak, 'tidak_ada', $alasan, $oleh);

        return $laporan;
    }

    /** @param  string  $tindakan  nonaktifkan|ubah_tujuan|tidak_ada */
    public function tandaiDitindaklanjuti(LaporanPenyalahgunaan $laporan, string $tindakan, ?string $catatan, User $oleh): LaporanPenyalahgunaan
    {
        $this->otorisasi($oleh);
        $this->pastikanTerbuka($laporan);

        if (! in_array($tindakan, ['nonaktifkan', 'ubah_tujuan', 'tidak_ada'], true)) {
            throw ValidationException::withMessages(['tindakan' => 'Tindakan tidak dikenal.']);
        }

        $this->selesaikan($laporan, StatusLaporan::Ditindaklanjuti, $tindakan, $catatan, $oleh);

        return $laporan;
    }

    private function selesaikan(LaporanPenyalahgunaan $laporan, StatusLaporan $status, string $tindakan, ?string $catatan, User $oleh): void
    {
        $laporan->update([
            'status' => $status,
            'tindakan' => $tindakan,
            'catatan_penanganan' => $catatan !== null ? trim($catatan) : null,
            'ditangani_oleh' => $oleh->getKey(),
            'ditangani_pada' => now(),
        ]);

        activity('moderasi')->performedOn($laporan)->causedBy($oleh)->event($status->value)
            ->withProperties(['tindakan' => $tindakan])->log('Laporan '.$status->getLabel());
    }

    private function otorisasi(User $oleh): void
    {
        Gate::forUser($oleh)->authorize(Izin::ModerasiKelola->value);
    }

    private function pastikanTerbuka(LaporanPenyalahgunaan $laporan): void
    {
        if (! in_array($laporan->status, [StatusLaporan::Baru, StatusLaporan::Ditinjau], true)) {
            throw ValidationException::withMessages(['status' => 'Laporan ini sudah selesai ditangani.']);
        }
    }
}
