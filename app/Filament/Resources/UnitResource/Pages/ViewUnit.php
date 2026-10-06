<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Resources\UnitResource;
use App\Models\Unit;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

/** Pengelola/anggota membuka unitnya (baca) dan, bila berhak, mengelola anggota lewat relation manager. */
class ViewUnit extends ViewRecord
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->visible(fn (Unit $record): bool => auth()->user()?->can('update', $record) ?? false)];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('kode')->label('Kode'),
            TextEntry::make('nama')->label('Nama'),
            TextEntry::make('jenis')->label('Jenis')->badge(),
            TextEntry::make('induk.nama')->label('Unit induk')->placeholder('—'),
            TextEntry::make('prefiks_slug')->label('Prefiks slug')->placeholder('—'),
        ])->columns(3);
    }
}
