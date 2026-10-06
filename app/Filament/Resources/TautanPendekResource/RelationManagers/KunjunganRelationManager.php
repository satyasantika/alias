<?php

namespace App\Filament\Resources\TautanPendekResource\RelationManagers;

use App\Enums\Izin;
use App\Filament\Exports\KunjunganExporter;
use App\Models\KunjunganTautan;
use App\Models\TautanPendek;
use Filament\Actions\ExportAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Baris kunjungan anonim (tanpa IP utuh, ip_hash, atau user agent). Hanya pemegang kunjungan.lihat-rinci. */
class KunjunganRelationManager extends RelationManager
{
    protected static string $relationship = 'kunjungan';

    protected static ?string $title = 'Kunjungan (anonim)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (auth()->user()?->can(Izin::KunjunganLihatRinci->value) ?? false)
            && $ownerRecord instanceof TautanPendek
            && (auth()->user()->can('view', $ownerRecord));
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('dikunjungi_pada', 'desc')
            ->columns([
                TextColumn::make('dikunjungi_pada')->label('Waktu')->dateTime('d F Y H:i')->sortable(),
                TextColumn::make('ip_anonim')->label('IP (dianonimkan)'),
                TextColumn::make('peramban')->label('Peramban')->description(fn (?KunjunganTautan $record): ?string => $record?->versi_peramban ? 'v'.$record->versi_peramban : null)->placeholder('—'),
                TextColumn::make('os')->label('OS')->placeholder('—'),
                TextColumn::make('jenis_perangkat')->label('Perangkat')->badge(),
                TextColumn::make('perujuk_host')->label('Perujuk')->placeholder('(langsung)'),
                IconColumn::make('bot')->label('Bot')->boolean(),
            ])
            ->filters([TernaryFilter::make('bot')->label('Bot')->default(false)])
            ->headerActions([
                ExportAction::make()->exporter(KunjunganExporter::class)->label('Ekspor kunjungan')
                    ->visible(fn (): bool => auth()->user()?->can(Izin::AnalitikEkspor->value) ?? false),
            ]);
    }
}
