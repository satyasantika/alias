<?php

namespace App\Filament\Resources\PenggunaResource\Pages;

use App\Filament\Imports\PenggunaImporter;
use App\Filament\Resources\PenggunaResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListPengguna extends ListRecords
{
    protected static string $resource = PenggunaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()->importer(PenggunaImporter::class)->label('Impor CSV')
                ->visible(fn (): bool => auth()->user()?->can('create', User::class) ?? false)
                ->maxRows(500),
            CreateAction::make()->label('Buat pengguna'),
        ];
    }
}
