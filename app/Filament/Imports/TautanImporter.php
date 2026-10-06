<?php

namespace App\Filament\Imports;

use App\Actions\Tautan\BuatTautan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Impor massal tautan (mis. dari bit.ly/s.id lama). Setiap baris melewati BuatTautan sehingga validasi slug,
 * tujuan (BR-05–08), dan status awal sama persis; kuota dan laju diabaikan (tautan.impor).
 */
class TautanImporter extends Importer
{
    protected static ?string $model = TautanPendek::class;

    /** @return array<ImportColumn> */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('judul')->label('Judul')->requiredMapping()->rules(['required', 'max:150'])->example('Pendaftaran Seminar Prodi'),
            ImportColumn::make('url_tujuan')->label('URL tujuan')->requiredMapping()->rules(['required', 'max:2048'])->example('https://forms.gle/contoh'),
            ImportColumn::make('slug')->label('Slug')->rules(['nullable', 'max:50'])->example('seminar-prodi-2026'),
            ImportColumn::make('jenis_kepemilikan')->label('Jenis kepemilikan')->rules(['nullable', 'in:pribadi,unit'])->example('unit'),
            ImportColumn::make('email_pemilik')->label('Surel pemilik (bila pribadi)')->rules(['nullable', 'email'])->example('dosen.a@unsil.ac.id'),
            ImportColumn::make('kode_unit')->label('Kode unit (bila unit)')->rules(['nullable', 'max:20'])->example('PMAT'),
            ImportColumn::make('aktif_sampai')->label('Aktif sampai')->rules(['nullable', 'date'])->example('2026-12-31 23:59'),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return new TautanPendek;
    }

    public function fillRecord(): void
    {
        // Penyimpanan seluruhnya lewat Action BuatTautan.
    }

    public function saveRecord(): void
    {
        $oleh = $this->import->user;
        if (! $oleh instanceof User) {
            throw new RowImportFailedException('Pengimpor tidak ditemukan.');
        }

        $baris = $this->data;
        $jenis = Str::lower(trim((string) ($baris['jenis_kepemilikan'] ?? ''))) ?: 'pribadi';

        $opsi = ['impor' => true];
        $data = [
            'url_tujuan' => $baris['url_tujuan'] ?? '',
            'judul' => $baris['judul'] ?? '',
            'slug_kustom' => filled($baris['slug'] ?? null) ? $baris['slug'] : null,
            'jenis_kepemilikan' => $jenis,
            'aktif_sampai' => filled($baris['aktif_sampai'] ?? null) ? $baris['aktif_sampai'] : null,
        ];

        if ($jenis === 'unit') {
            $unit = Unit::query()->where('kode', Str::upper(trim((string) ($baris['kode_unit'] ?? ''))))->first();
            if ($unit === null) {
                throw new RowImportFailedException('Kode unit tidak ditemukan.');
            }
            $data['unit_id'] = $unit->getKey();
        } else {
            $pemilik = User::query()->where('email', Str::lower(trim((string) ($baris['email_pemilik'] ?? ''))))->first();
            if ($pemilik === null) {
                throw new RowImportFailedException('Pemilik dengan surel tersebut tidak ditemukan.');
            }
            $opsi['pemilik'] = $pemilik;
        }

        try {
            $this->record = app(BuatTautan::class)->jalankan($data, $oleh, $opsi);
        } catch (ValidationException $e) {
            throw new RowImportFailedException((string) collect($e->errors())->flatten()->first());
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $isi = 'Impor tautan selesai: '.Number::format($import->successful_rows).' '.str('baris')->plural($import->successful_rows).' berhasil.';

        if ($gagal = $import->getFailedRowsCount()) {
            $isi .= ' '.Number::format($gagal).' baris gagal; unduh daftar kegagalan untuk melihat alasannya.';
        }

        return $isi;
    }
}
