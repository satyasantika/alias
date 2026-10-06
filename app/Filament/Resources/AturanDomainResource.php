<?php

namespace App\Filament\Resources;

use App\Enums\JenisAturanDomain;
use App\Filament\Resources\AturanDomainResource\Pages\ManageAturanDomain;
use App\Models\AturanDomain;
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

class AturanDomainResource extends Resource
{
    protected static ?string $model = AturanDomain::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Moderasi';

    protected static ?string $modelLabel = 'aturan domain';

    protected static ?string $pluralModelLabel = 'aturan domain';

    protected static ?string $slug = 'aturan-domain';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('pola_host')->label('Pola host')->required()->maxLength(255)->unique(ignoreRecord: true)
                ->helperText('Contoh: bit.ly (persis) atau *.xyz (host dan semua subdomain).')
                ->regex('/^(\*\.)?[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i'),
            Select::make('jenis')->label('Jenis')->options(JenisAturanDomain::class)->required(),
            TextInput::make('alasan')->label('Alasan')->maxLength(255),
            Toggle::make('aktif')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('pola_host')
            ->columns([
                TextColumn::make('pola_host')->label('Pola host')->searchable()->sortable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('alasan')->label('Alasan')->limit(40),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([SelectFilter::make('jenis')->label('Jenis')->options(JenisAturanDomain::class)])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'dibuat_oleh' => auth()->id()]),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAturanDomain::route('/')];
    }
}
