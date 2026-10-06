<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/** STANDAR-TEKNIS §1a / 02 §10: satu-satunya unggahan adalah CSV impor (ImportAction); QR & ekspor tidak permanen. */
it('tidak memakai FileUpload atau penyimpanan permanen di kode aplikasi', function () {
    $dilarang = ['FileUpload', 'SpatieMediaLibrary', '->storePublicly(', '->storeAs(', 'Storage::disk(\'public\')', "Storage::put('"];

    foreach (File::allFiles(app_path()) as $berkas) {
        $isi = $berkas->getContents();
        foreach ($dilarang as $kata) {
            expect(str_contains($isi, $kata))->toBeFalse("{$berkas->getRelativePathname()} memuat {$kata}");
        }
    }
});

it('hanya mengizinkan impor CSV sebagai jalur unggahan melalui ImportAction', function () {
    $pemakai = [];
    foreach (File::allFiles(app_path()) as $berkas) {
        if (str_contains($berkas->getContents(), 'ImportAction::make')) {
            $pemakai[] = $berkas->getRelativePathname();
        }
    }

    expect($pemakai)->toBe(['Filament/Resources/TautanPendekResource/Pages/ListTautanPendek.php']);
});

it('tidak menyimpan QR ke disk dan memakai disk tmp privat untuk ekspor', function () {
    expect(file_get_contents(app_path('Http/Controllers/QrTautanController.php')))->not->toContain('Storage::')->not->toContain('file_put_contents');
    expect(config('filesystems.disks.tmp.root'))->toBe(storage_path('app/tmp'))->and(config('filesystems.disks.tmp.visibility'))->not->toBe('public');
});

it('tidak memiliki rute unggah selain milik Livewire/Filament', function () {
    $rute = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => in_array('POST', $r->methods()) && str_contains($r->uri(), 'upload'));

    foreach ($rute as $r) {
        expect($r->uri())->toStartWith('livewire');
    }
});
