<?php

namespace App\Filament\Resources\TautanPendekResource\Pages;

use App\Actions\Tautan\UbahTautan;
use App\Filament\Resources\TautanPendekResource;
use App\Models\TautanPendek;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditTautanPendek extends EditRecord
{
    protected static string $resource = TautanPendekResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var TautanPendek $record */
        try {
            return app(UbahTautan::class)->jalankan($record, $data, auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($pesan, $kunci) => ["data.{$kunci}" => $pesan])->all()
            );
        }
    }
}
