<?php

namespace App\Filament\Resources;

use App\Actions\Moderasi\TanganiLaporan;
use App\Enums\Izin;
use App\Enums\KategoriLaporan;
use App\Enums\StatusLaporan;
use App\Filament\Exports\LaporanModerasiExporter;
use App\Filament\Resources\LaporanPenyalahgunaanResource\Pages\ListLaporanPenyalahgunaan;
use App\Models\LaporanPenyalahgunaan;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class LaporanPenyalahgunaanResource extends Resource
{
    protected static ?string $model = LaporanPenyalahgunaan::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'Moderasi';

    protected static ?string $modelLabel = 'laporan penyalahgunaan';

    protected static ?string $pluralModelLabel = 'laporan penyalahgunaan';

    protected static ?string $slug = 'laporan-penyalahgunaan';

    public static function getNavigationBadge(): ?string
    {
        $jumlah = LaporanPenyalahgunaan::query()->where('status', StatusLaporan::Baru)->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('tautan')->addSelect([
                'laporan_penyalahgunaan.*',
                'jumlah_laporan' => LaporanPenyalahgunaan::query()->from('laporan_penyalahgunaan as l2')->selectRaw('count(*)')
                    ->whereColumn('l2.tautan_pendek_id', 'laporan_penyalahgunaan.tautan_pendek_id'),
            ]))
            ->columns([
                TextColumn::make('created_at')->label('Masuk')->since()->sortable()->tooltip(fn (LaporanPenyalahgunaan $r): string => $r->created_at->format('d F Y H:i')),
                TextColumn::make('kode_dilaporkan')->label('Kode')->searchable()->description(fn (LaporanPenyalahgunaan $r): ?string => $r->tautan?->judul),
                TextColumn::make('tautan.url_tujuan')->label('Tujuan')->limit(40)->placeholder('—')->tooltip(fn (LaporanPenyalahgunaan $r): ?string => $r->tautan?->url_tujuan),
                TextColumn::make('kategori')->label('Kategori')->badge(),
                TextColumn::make('jumlah_laporan')->label('Laporan/tautan')->placeholder('1')->alignCenter(),
                TextColumn::make('keterangan')->label('Keterangan')->limit(50)->wrap()->toggleable(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(StatusLaporan::class)->default(StatusLaporan::Baru->value),
                SelectFilter::make('kategori')->label('Kategori')->options(KategoriLaporan::class),
            ])
            ->headerActions([
                ExportAction::make()->exporter(LaporanModerasiExporter::class)->label('Ekspor rekap (XLSX)')
                    ->visible(fn (): bool => auth()->user()?->can(Izin::ModerasiKelola->value) ?? false),
            ])
            ->recordActions([
                Action::make('tinjau')->label('Tinjau')->icon(Heroicon::OutlinedEye)
                    ->visible(self::bila(StatusLaporan::Baru))
                    ->action(fn (LaporanPenyalahgunaan $record) => self::jalankan(fn () => app(TanganiLaporan::class)->tinjau($record, auth()->user()), 'Laporan diambil untuk ditinjau')),
                Action::make('blokir')->label('Blokir tautan')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (LaporanPenyalahgunaan $r): bool => $r->tautan_pendek_id !== null && self::terbuka($r))
                    ->schema([Textarea::make('alasan')->label('Alasan blokir')->required()->minLength(10)->maxLength(500)])
                    ->action(fn (LaporanPenyalahgunaan $record, array $data) => self::jalankan(fn () => app(TanganiLaporan::class)->blokirTautan($record, $data['alasan'], auth()->user()), 'Tautan diblokir')),
                Action::make('tolak')->label('Tolak laporan')->icon(Heroicon::OutlinedXMark)->color('gray')
                    ->visible(fn (LaporanPenyalahgunaan $r): bool => self::terbuka($r))
                    ->schema([Textarea::make('alasan')->label('Alasan penolakan')->required()->minLength(5)->maxLength(500)])
                    ->action(fn (LaporanPenyalahgunaan $record, array $data) => self::jalankan(fn () => app(TanganiLaporan::class)->tolak($record, $data['alasan'], auth()->user()), 'Laporan ditolak')),
                Action::make('tindaklanjuti')->label('Tandai ditindaklanjuti')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (LaporanPenyalahgunaan $r): bool => self::terbuka($r))
                    ->schema([
                        Select::make('tindakan')->label('Tindakan')->required()->options(['nonaktifkan' => 'Tautan dinonaktifkan', 'ubah_tujuan' => 'Tujuan diubah', 'tidak_ada' => 'Tidak ada tindakan']),
                        Textarea::make('catatan')->label('Catatan')->maxLength(500),
                    ])
                    ->action(fn (LaporanPenyalahgunaan $record, array $data) => self::jalankan(fn () => app(TanganiLaporan::class)->tandaiDitindaklanjuti($record, $data['tindakan'], $data['catatan'] ?? null, auth()->user()), 'Laporan ditandai ditindaklanjuti')),
            ]);
    }

    private static function terbuka(LaporanPenyalahgunaan $r): bool
    {
        return in_array($r->status, [StatusLaporan::Baru, StatusLaporan::Ditinjau], true);
    }

    private static function bila(StatusLaporan $status): Closure
    {
        return fn (LaporanPenyalahgunaan $r): bool => $r->status === $status;
    }

    private static function jalankan(Closure $tindakan, string $berhasil): void
    {
        try {
            $tindakan();
            Notification::make()->title($berhasil)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => ListLaporanPenyalahgunaan::route('/')];
    }
}
