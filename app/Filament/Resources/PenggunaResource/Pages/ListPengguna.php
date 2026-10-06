<?php

namespace App\Filament\Resources\PenggunaResource\Pages;

use App\Filament\Resources\PenggunaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPengguna extends ListRecords
{
    protected static string $resource = PenggunaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat pengguna')];
    }
}
