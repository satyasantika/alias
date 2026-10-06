<?php

namespace App\Filament\Resources\TautanPendekResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RiwayatKepemilikanRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayatKepemilikan';

    protected static ?string $title = 'Riwayat kepemilikan';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['pelaku']))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i'),
                TextColumn::make('dari_jenis')->label('Dari')->badge(),
                TextColumn::make('ke_jenis')->label('Ke')->badge(),
                TextColumn::make('pelaku.name')->label('Oleh'),
                TextColumn::make('alasan')->label('Alasan')->wrap()->placeholder('—'),
            ])
            ->paginated(false);
    }
}
