<?php

namespace App\Filament\Resources\TautanPendekResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RiwayatStatusRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayatStatus';

    protected static ?string $title = 'Riwayat status';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('pelaku'))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i'),
                TextColumn::make('dari_status')->label('Dari')->badge()->placeholder('—'),
                TextColumn::make('ke_status')->label('Ke')->badge(),
                TextColumn::make('pelaku.name')->label('Oleh')->placeholder('Sistem'),
                TextColumn::make('alasan')->label('Alasan')->wrap()->placeholder('—'),
            ])
            ->paginated(false);
    }
}
