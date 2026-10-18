<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\File;

it('memakai HasUuids pada semua model aplikasi', function () {
    foreach (File::files(app_path('Models')) as $file) {
        $kelas = 'App\\Models\\'.$file->getFilenameWithoutExtension();
        // Role & Permission (spatie) dan Activity memakai HasUuids lewat trait/turunan kelas sendiri.
        expect(in_array(HasUuids::class, class_uses_recursive($kelas), true))->toBeTrue($kelas.' harus memakai HasUuids');
    }
});

it('tidak memakai kunci auto-increment pada migrasi aplikasi', function () {
    $terlarang = ['->id(', 'foreignId(', ' morphs(', 'nullableMorphs(', 'bigIncrements(', ' increments(', 'HasUlids'];

    foreach (File::files(database_path('migrations')) as $file) {
        // Tabel antrean kerangka kerja memakai id auto-increment bawaan Laravel (§4a butir 6).
        if (str_contains($file->getFilename(), 'perbaiki_kunci_tabel_jobs_dan_failed_jobs')) {
            continue;
        }

        $isi = $file->getContents();
        foreach ($terlarang as $kata) {
            expect(str_contains($isi, $kata))->toBeFalse($file->getFilename().' memuat '.trim($kata));
        }
    }
});

it('tidak memakai char/binary eksplisit untuk kolom id, FK, atau morph di migrasi', function () {
    // §4a: seluruh kolom id/FK/morph lewat helper Laravel 13 (uuid, foreignUuid, uuidMorphs) agar tipenya sama
    // (UUID native MariaDB); MariaDB menolak FK antar tipe berbeda.
    $terlarang = [
        '/->char\(\s*[\'"][a-z_]*id[a-z_]*[\'"]\s*,\s*36/' => "char('..._id', 36)",
        '/->binary\(\s*[\'"][a-z_]*id[a-z_]*[\'"]\s*,\s*16/' => "binary('..._id', 16)",
    ];

    foreach (File::files(database_path('migrations')) as $file) {
        foreach ($terlarang as $pola => $nama) {
            expect(preg_match($pola, $file->getContents()))->toBe(0, $file->getFilename().' memuat '.$nama);
        }
    }
});
