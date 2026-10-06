<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

/** Membaca database/seeders/data/unit.csv (kode,nama,jenis,kode_induk,prefiks_slug). Idempoten. */
class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $berkas = fopen(database_path('seeders/data/unit.csv'), 'r');
        $header = fgetcsv($berkas, escape: '');

        $baris = [];
        while (($kolom = fgetcsv($berkas, escape: '')) !== false) {
            if ($kolom === [null]) {
                continue;
            }
            $baris[] = array_combine($header, $kolom);
        }
        fclose($berkas);

        // Induk dibuat lebih dahulu.
        usort($baris, fn (array $a, array $b) => ($a['kode_induk'] !== '') <=> ($b['kode_induk'] !== ''));

        foreach ($baris as $data) {
            Unit::updateOrCreate(['kode' => $data['kode']], [
                'nama' => $data['nama'],
                'jenis' => $data['jenis'],
                'induk_id' => $data['kode_induk'] !== '' ? Unit::where('kode', $data['kode_induk'])->value('id') : null,
                'prefiks_slug' => $data['prefiks_slug'] !== '' ? $data['prefiks_slug'] : null,
            ]);
        }
    }
}
