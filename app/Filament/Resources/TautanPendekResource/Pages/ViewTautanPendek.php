<?php

namespace App\Filament\Resources\TautanPendekResource\Pages;

use App\Filament\Resources\TautanPendekResource;
use App\Filament\Resources\TautanPendekResource\AksiStatus;
use App\Filament\Widgets\Tautan\KlikHarianChart;
use App\Filament\Widgets\Tautan\PerangkatChart;
use App\Filament\Widgets\Tautan\PerujukTeratasTable;
use App\Models\TautanPendek;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTautanPendek extends ViewRecord
{
    protected static string $resource = TautanPendekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (TautanPendek $record): bool => auth()->user()?->can('update', $record) ?? false),
            ...AksiStatus::semua(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('judul')->label('Judul'),
            TextEntry::make('url_pendek')->label('URL pendek')->copyable(),
            TextEntry::make('url_tujuan')->label('Tujuan')->url(fn (TautanPendek $r): string => $r->url_tujuan)->openUrlInNewTab(),
            TextEntry::make('status')->label('Status')->badge(),
            TextEntry::make('status_efektif')->label('Efektif')->badge()->state(fn (TautanPendek $r) => $r->statusEfektif()),
            TextEntry::make('jumlah_klik')->label('Klik manusia')->numeric(),
            TextEntry::make('status_cek_tujuan')->label('Cek tujuan')->badge(),
            TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
        ])->columns(2);
    }

    /** @return array<class-string> */
    protected function getFooterWidgets(): array
    {
        return [KlikHarianChart::class, PerangkatChart::class, PerujukTeratasTable::class];
    }
}
