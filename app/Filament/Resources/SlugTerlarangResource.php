<?php

namespace App\Filament\Resources;

use App\Enums\CaraCocok;
use App\Enums\JenisSlugTerlarang;
use App\Filament\Resources\SlugTerlarangResource\Pages\ManageSlugTerlarang;
use App\Models\SlugTerlarang;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
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

class SlugTerlarangResource extends Resource
{
    protected static ?string $model = SlugTerlarang::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|\UnitEnum|null $navigationGroup = 'Moderasi';

    protected static ?string $modelLabel = 'slug terlarang';

    protected static ?string $pluralModelLabel = 'slug terlarang';

    protected static ?string $slug = 'slug-terlarang';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('pola')->label('Pola')->required()->maxLength(64)
                ->dehydrateStateUsing(fn (string $state): string => mb_strtolower(trim($state))),
            Select::make('jenis')->label('Jenis')->options(JenisSlugTerlarang::class)->required()
                ->default(JenisSlugTerlarang::CadanganKelembagaan->value),
            Select::make('cara_cocok')->label('Cara mencocokkan')->options(CaraCocok::class)->required()
                ->default(CaraCocok::Persis->value),
            TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
            Toggle::make('aktif')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('pola')
            ->columns([
                TextColumn::make('pola')->label('Pola')->searchable()->sortable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('cara_cocok')->label('Cocok')->badge()->color('gray'),
                TextColumn::make('keterangan')->label('Keterangan')->limit(40)->toggleable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([SelectFilter::make('jenis')->label('Jenis')->options(JenisSlugTerlarang::class)])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'dibuat_oleh' => auth()->id()]),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSlugTerlarang::route('/')];
    }
}
