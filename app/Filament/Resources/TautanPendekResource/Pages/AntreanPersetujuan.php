<?php

namespace App\Filament\Resources\TautanPendekResource\Pages;

use App\Enums\Izin;
use App\Enums\StatusTautan;
use App\Filament\Resources\TautanPendekResource;
use App\Filament\Resources\TautanPendekResource\AksiStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Antrean persetujuan slug kustom (tautan.setujui). */
class AntreanPersetujuan extends ListRecords
{
    protected static string $resource = TautanPendekResource::class;

    protected static ?string $title = 'Antrean persetujuan slug';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can(Izin::TautanSetujui->value) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => TautanPendekResource::getEloquentQuery()->where('status', StatusTautan::MenungguPersetujuan->value))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('kode')->label('Slug')->searchable(),
                TextColumn::make('judul')->label('Judul')->limit(40),
                TextColumn::make('host_tujuan')->label('Tujuan'),
                TextColumn::make('pembuat.name')->label('Diajukan oleh'),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d F Y H:i')->sortable(),
            ])
            ->recordActions(AksiStatus::semua())
            ->headerActions([])
            ->filters([]);
    }
}
