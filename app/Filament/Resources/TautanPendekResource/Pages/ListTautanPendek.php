<?php

namespace App\Filament\Resources\TautanPendekResource\Pages;

use App\Enums\Izin;
use App\Enums\JenisKepemilikan;
use App\Enums\StatusCekTujuan;
use App\Enums\StatusTautan;
use App\Filament\Imports\TautanImporter;
use App\Filament\Resources\TautanPendekResource;
use App\Models\TautanPendek;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListTautanPendek extends ListRecords
{
    protected static string $resource = TautanPendekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()->importer(TautanImporter::class)->label('Impor CSV')->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn (): bool => auth()->user()?->can(Izin::TautanImpor->value) ?? false)
                ->maxRows(2000),
            CreateAction::make()->label('Buat tautan')->visible(fn (): bool => auth()->user()?->can('create', TautanPendek::class) ?? false)];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'milik-saya' => Tab::make('Milik saya')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('jenis_kepemilikan', JenisKepemilikan::Pribadi->value)->where('pemilik_id', auth()->id())),
            'unit' => Tab::make('Unit')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('jenis_kepemilikan', JenisKepemilikan::Unit->value)),
            'menunggu' => Tab::make('Menunggu')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StatusTautan::MenungguPersetujuan->value)),
            'bermasalah' => Tab::make('Bermasalah')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(fn (Builder $w) => $w
                    ->whereIn('status', [StatusTautan::Diblokir->value, StatusTautan::Ditolak->value])
                    ->orWhere('status_cek_tujuan', StatusCekTujuan::Bermasalah->value))),
        ];
    }
}
