<?php

namespace App\Filament\Resources\PenggunaResource\Pages;

use App\Actions\Pengguna\AturPeranPengguna;
use App\Filament\Resources\PenggunaResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditPengguna extends EditRecord
{
    protected static string $resource = PenggunaResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();
        $data['peran'] = $record->roles->pluck('name')->all();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $peran = $data['peran'] ?? [];
        unset($data['peran']);

        try {
            app(AturPeranPengguna::class)->jalankan($record, $peran, auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.peran' => $e->errors()['roles'] ?? $e->getMessage()]);
        }

        $record->update($data);

        return $record;
    }
}
