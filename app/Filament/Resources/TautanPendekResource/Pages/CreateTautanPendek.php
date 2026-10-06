<?php

namespace App\Filament\Resources\TautanPendekResource\Pages;

use App\Actions\Tautan\BuatTautan;
use App\Filament\Resources\TautanPendekResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateTautanPendek extends CreateRecord
{
    protected static string $resource = TautanPendekResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(BuatTautan::class)->jalankan($data, auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($pesan, $kunci) => ["data.{$kunci}" => $pesan])->all()
            );
        }
    }
}
