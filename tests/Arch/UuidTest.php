<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        $isi = $file->getContents();
        foreach ($terlarang as $kata) {
            expect(str_contains($isi, $kata))->toBeFalse($file->getFilename().' memuat '.trim($kata));
        }
    }
});

it('membangkitkan UUID versi 7 untuk pengguna', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and($user->id[14])->toBe('7');
});

it('menjalankan route model binding dengan UUID', function () {
    $user = User::factory()->create();

    expect(User::query()->whereKey($user->id)->firstOrFail()->is($user))->toBeTrue();
    expect($user->getKeyType())->toBe('string')->and($user->getIncrementing())->toBeFalse();
});
