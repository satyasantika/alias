<?php

namespace App\Filament\Imports;

use App\Actions\Pengguna\BuatPengguna;
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
 * Impor massal pengguna. Setiap baris melewati BuatPengguna sehingga validasi domain surel (BR-31/BR-32),
 * larangan memberi peran admin tanpa izin (BR-33), dan pengiriman surel atur kata sandi sama persis
 * dengan pembuatan satu per satu lewat formulir.
 */
class PenggunaImporter extends Importer
{
    protected static ?string $model = User::class;

    /** @return array<ImportColumn> */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')->label('Nama lengkap')->requiredMapping()->rules(['required', 'max:255'])->example('Dosen Baru'),
            ImportColumn::make('email')->label('Surel')->requiredMapping()->rules(['required', 'email', 'max:255'])->example('dosen.baru@unsil.ac.id'),
            ImportColumn::make('nip')->label('NIP')->rules(['nullable', 'max:30'])->example('198001012005011001'),
            ImportColumn::make('nidn')->label('NIDN')->rules(['nullable', 'max:20'])->example('0012345601'),
            ImportColumn::make('unit_kode')->label('Kode unit homebase')->rules(['nullable', 'max:20'])->example('PMAT'),
            ImportColumn::make('kuota_tautan')->label('Kuota tautan')->rules(['nullable', 'integer', 'min:0'])->example('100'),
            ImportColumn::make('peran')->label('Peran (pisahkan dengan |)')->rules(['nullable', 'max:255'])->example('pengguna'),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return new User;
    }

    public function fillRecord(): void
    {
        // Penyimpanan seluruhnya lewat Action BuatPengguna.
    }

    public function saveRecord(): void
    {
        $oleh = $this->import->user;
        if (! $oleh instanceof User) {
            throw new RowImportFailedException('Pengimpor tidak ditemukan.');
        }

        $baris = $this->data;
        $unit = null;
        if (filled($baris['unit_kode'] ?? null)) {
            $unit = Unit::query()->where('kode', Str::upper(trim((string) $baris['unit_kode'])))->first();
            if ($unit === null) {
                throw new RowImportFailedException('Kode unit tidak ditemukan.');
            }
        }

        $peran = filled($baris['peran'] ?? null)
            ? array_values(array_filter(array_map('trim', explode('|', (string) $baris['peran']))))
            : [];

        $data = [
            'name' => $baris['name'] ?? '',
            'email' => $baris['email'] ?? '',
            'nip' => filled($baris['nip'] ?? null) ? $baris['nip'] : null,
            'nidn' => filled($baris['nidn'] ?? null) ? $baris['nidn'] : null,
            'unit_id' => $unit?->getKey(),
            'kuota_tautan' => filled($baris['kuota_tautan'] ?? null) ? (int) $baris['kuota_tautan'] : null,
            'peran' => $peran,
        ];

        try {
            $this->record = app(BuatPengguna::class)->jalankan($data, $oleh);
        } catch (ValidationException $e) {
            throw new RowImportFailedException((string) collect($e->errors())->flatten()->first());
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $isi = 'Impor pengguna selesai: '.Number::format($import->successful_rows).' '.str('baris')->plural($import->successful_rows).' berhasil.';

        if ($gagal = $import->getFailedRowsCount()) {
            $isi .= ' '.Number::format($gagal).' baris gagal; unduh daftar kegagalan untuk melihat alasannya.';
        }

        return $isi;
    }
}
