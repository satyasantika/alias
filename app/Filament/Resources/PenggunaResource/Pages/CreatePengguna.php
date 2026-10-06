<?php

namespace App\Filament\Resources\PenggunaResource\Pages;

use App\Actions\Pengguna\BuatPengguna;
use App\Filament\Resources\PenggunaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePengguna extends CreateRecord
{
    protected static string $resource = PenggunaResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(BuatPengguna::class)->jalankan($data, auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($pesan, $kunci) => ["data.{$kunci}" => $pesan])->all()
            );
        }
    }
}
