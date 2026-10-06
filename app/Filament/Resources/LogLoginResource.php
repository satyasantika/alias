<?php

namespace App\Filament\Resources;

use App\Enums\PeristiwaLogin;
use App\Filament\Resources\LogLoginResource\Pages\ListLogLogins;
use App\Models\LogLogin;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LogLoginResource extends Resource
{
    protected static ?string $model = LogLogin::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'log login';

    protected static ?string $pluralModelLabel = 'log login';

    protected static ?string $slug = 'log-login';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i:s')->sortable(),
                TextColumn::make('email')->label('Surel')->searchable(),
                TextColumn::make('peristiwa')->label('Peristiwa')->badge(),
                TextColumn::make('metode')->label('Metode')->badge()->color('gray'),
                TextColumn::make('ip')->label('IP')->searchable(),
                TextColumn::make('user_agent')->label('Peramban')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('peristiwa')
                    ->label('Peristiwa')
                    ->options(PeristiwaLogin::class),
                Filter::make('rentang')
                    ->schema([
                        DatePicker::make('dari')->label('Dari'),
                        DatePicker::make('sampai')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogLogins::route('/'),
        ];
    }
}
