<?php

namespace App\Filament\Resources;

use App\Enums\Izin;
use App\Enums\JenisUnit;
use App\Enums\Peran;
use App\Filament\Resources\UnitResource\Pages\CreateUnit;
use App\Filament\Resources\UnitResource\Pages\EditUnit;
use App\Filament\Resources\UnitResource\Pages\ListUnit;
use App\Filament\Resources\UnitResource\RelationManagers\AnggotaRelationManager;
use App\Models\Unit;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?string $modelLabel = 'unit';

    protected static ?string $pluralModelLabel = 'unit';

    protected static ?string $slug = 'unit';

    protected static ?string $recordTitleAttribute = 'nama';

    /** Pemantau (nama saja) dan admin: semua unit; pengguna/pengelola: hanya unit tempat ia terlibat. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null || $user->can(Izin::UnitKelola->value) || $user->hasRole(Peran::Pemantau->value)) {
            return $query;
        }

        return $query->whereIn('id', $user->unitAnggota()->select('unit.id'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->label('Kode')->required()->maxLength(20)->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state))),
            TextInput::make('nama')->label('Nama')->required()->maxLength(150),
            Select::make('jenis')->label('Jenis')->options(JenisUnit::class)->required(),
            Select::make('induk_id')->label('Unit induk')
                ->relationship('induk', 'nama', fn (Builder $q, ?Unit $record) => $q->when($record, fn ($q) => $q->whereKeyNot($record->getKey())))
                ->searchable()->preload(),
            TextInput::make('prefiks_slug')->label('Prefiks slug')
                ->regex('/^[a-z0-9]{2,20}$/')->maxLength(20)->unique(ignoreRecord: true)
                ->helperText('Opsional, 2–20 huruf kecil/angka (namespace slug unit).'),
            TextInput::make('kuota_tautan')->label('Kuota tautan')->numeric()->minValue(0)
                ->helperText('Kosong = pakai kuota bawaan unit.'),
            Toggle::make('aktif')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('kode')
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('induk.nama')->label('Induk')->placeholder('-'),
                TextColumn::make('keanggotaan_count')->label('Anggota')->counts('keanggotaan')
                    ->visible(fn (): bool => ! auth()->user()?->hasRole(Peran::Pemantau->value) || auth()->user()->can(Izin::UnitKelola->value)),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('jenis')->label('Jenis')->options(JenisUnit::class),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [AnggotaRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnit::route('/'),
            'create' => CreateUnit::route('/create'),
            'edit' => EditUnit::route('/{record}/edit'),
        ];
    }
}
