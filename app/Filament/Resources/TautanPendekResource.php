<?php

namespace App\Filament\Resources;

use App\Actions\Tautan\PindahkanKepemilikanTautan;
use App\Enums\Izin;
use App\Enums\JenisKepemilikan;
use App\Enums\KodeRedirect;
use App\Enums\StatusCekTujuan;
use App\Enums\StatusTautan;
use App\Filament\Resources\TautanPendekResource\AksiStatus;
use App\Filament\Resources\TautanPendekResource\Pages\AntreanPersetujuan;
use App\Filament\Resources\TautanPendekResource\Pages\CreateTautanPendek;
use App\Filament\Resources\TautanPendekResource\Pages\EditTautanPendek;
use App\Filament\Resources\TautanPendekResource\Pages\ListTautanPendek;
use App\Filament\Resources\TautanPendekResource\RelationManagers\RiwayatKepemilikanRelationManager;
use App\Filament\Resources\TautanPendekResource\RelationManagers\RiwayatStatusRelationManager;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Rules\SlugKustomValid;
use App\Rules\UrlTujuanValid;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TautanPendekResource extends Resource
{
    protected static ?string $model = TautanPendek::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = 'Tautan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'tautan';

    protected static ?string $pluralModelLabel = 'tautan';

    protected static ?string $slug = 'tautan';

    protected static ?string $recordTitleAttribute = 'judul';

    /** BR-20: hanya tautan yang terlihat oleh pengguna. */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<TautanPendek> $query */
        $query = parent::getEloquentQuery()->with(['pemilik', 'unit']);
        $user = auth()->user();

        /** @var Builder<Model> $hasil */
        $hasil = $user === null ? $query->whereRaw('0 = 1') : $query->terlihatOleh($user);

        return $hasil;
    }

    /** @return array<string, string> */
    public static function opsiUnit(): array
    {
        $user = auth()->user();
        $unit = $user === null || $user->adalahAdmin() ? Unit::query() : $user->unitAnggota();

        return $unit->where('unit.aktif', true)->orderBy('unit.nama')->pluck('unit.nama', 'unit.id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema->components([
            Section::make('Tujuan')->schema([
                TextInput::make('url_tujuan')->label('URL tujuan')->required()->maxLength(2048)
                    ->placeholder('https://docs.google.com/forms/...')
                    ->rules([fn () => new UrlTujuanValid(auth()->user())])
                    ->columnSpanFull(),
                TextInput::make('judul')->label('Judul')->required()->maxLength(150)->columnSpanFull(),
                Textarea::make('keterangan')->label('Catatan internal')->maxLength(500)->rows(2)->columnSpanFull(),
            ]),
            Section::make('Kode pendek')->schema([
                Toggle::make('pakai_slug')->label('Pakai slug kustom')->live()->dehydrated(false)
                    ->visible(fn (string $operation): bool => $operation === 'create' && ($user?->can(Izin::TautanSlugKustom->value) ?? false))
                    ->helperText('Tanpa slug kustom, kode acak 7 karakter dibuat otomatis.'),
                TextInput::make('slug_kustom')->label('Slug')->maxLength(50)->live(onBlur: true)
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('pakai_slug'))
                    ->required(fn (Get $get): bool => (bool) $get('pakai_slug'))
                    ->rules([fn (Get $get) => new SlugKustomValid(auth()->user(), filled($get('unit_id')) ? Unit::find($get('unit_id')) : null)])
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::lower(trim($state)) : null),
                Text::make(fn (Get $get, ?TautanPendek $record): string => 'URL pendek: '.self::pratinjauUrlPendek($record, $get('slug_kustom'))),
            ]),
            Section::make('Kepemilikan')->schema([
                Radio::make('jenis_kepemilikan')->label('Dimiliki oleh')->options(JenisKepemilikan::class)
                    ->default(JenisKepemilikan::Pribadi->value)->live()->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit')->dehydrated(),
                Select::make('unit_id')->label('Unit')->options(fn (): array => self::opsiUnit())->searchable()
                    ->visible(fn (Get $get): bool => $get('jenis_kepemilikan') === JenisKepemilikan::Unit->value)
                    ->required(fn (Get $get): bool => $get('jenis_kepemilikan') === JenisKepemilikan::Unit->value)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')->dehydrated(),
            ])->columns(2),
            Section::make('Jadwal & batas')->schema([
                DateTimePicker::make('aktif_mulai')->label('Aktif mulai'),
                DateTimePicker::make('aktif_sampai')->label('Aktif sampai')->after('aktif_mulai'),
                TextInput::make('batas_klik')->label('Batas klik')->numeric()->minValue(1),
                Toggle::make('sekali_pakai')->label('Sekali pakai')->helperText('Hanya klik manusia pertama yang dialihkan.'),
            ])->columns(2)->collapsed(),
            Section::make('Perlindungan')->schema([
                TextInput::make('kata_sandi')->label('Kata sandi tautan (opsional)')->password()->revealable()->minLength(4)->maxLength(100)
                    ->autocomplete('new-password')->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Pengunjung harus memasukkan kata sandi sebelum dialihkan. Kosongkan bila tidak diperlukan.'),
                Toggle::make('hapus_kata_sandi')->label('Hapus kata sandi yang ada')
                    ->visible(fn (?TautanPendek $record): bool => $record?->kata_sandi_hash !== null),
            ])->columns(2)->collapsed(),
            Section::make('Lanjutan')->schema([
                Toggle::make('teruskan_query')->label('Teruskan parameter query ke tujuan'),
                Select::make('kode_status_redirect')->label('Kode redirect')->options(KodeRedirect::class)->default(302)
                    ->visible(fn (): bool => $user?->can(Izin::TautanAturLanjutan->value) ?? false),
                Toggle::make('catat_kunjungan')->label('Catat kunjungan')->default(true)
                    ->visible(fn (): bool => $user?->can(Izin::TautanAturLanjutan->value) ?? false),
            ])->columns(2)->collapsed(),
        ]);
    }

    public static function pratinjauUrlPendek(?TautanPendek $record, mixed $slug): string
    {
        if ($record !== null) {
            return $record->url_pendek;
        }

        $dasar = config('alias.domain_pendek') ? 'https://'.config('alias.domain_pendek') : rtrim((string) config('app.url'), '/');

        return $dasar.'/'.(filled($slug) ? Str::lower(trim((string) $slug)) : '〈kode acak〉');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->copyable()
                    ->copyableState(fn (TautanPendek $r): string => $r->url_pendek)->copyMessage('URL pendek disalin')
                    ->description(fn (TautanPendek $r): string => $r->url_pendek),
                TextColumn::make('judul')->label('Judul')->searchable()->limit(40)->wrap(),
                TextColumn::make('host_tujuan')->label('Tujuan')->searchable()->toggleable(),
                TextColumn::make('pemilik_nama')->label('Pemilik')
                    ->state(fn (TautanPendek $r): string => $r->jenis_kepemilikan === JenisKepemilikan::Unit ? (string) $r->unit?->nama : (string) $r->pemilik?->name),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('status_efektif')->label('Efektif')->badge()
                    ->state(fn (TautanPendek $r) => $r->statusEfektif())->toggleable(),
                TextColumn::make('jumlah_klik')->label('Klik')->numeric()->sortable(),
                IconColumn::make('status_cek_tujuan')->label('Tujuan')
                    ->icon(fn (StatusCekTujuan $state): Heroicon => match ($state) {
                        StatusCekTujuan::Sehat => Heroicon::OutlinedCheckCircle,
                        StatusCekTujuan::Bermasalah => Heroicon::OutlinedExclamationTriangle,
                        StatusCekTujuan::Terbatas => Heroicon::OutlinedLockClosed,
                        default => Heroicon::OutlinedMinusCircle,
                    })
                    ->color(fn (StatusCekTujuan $state): string => $state->getColor())
                    ->tooltip(fn (StatusCekTujuan $state): string => $state->getLabel()),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d F Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(StatusTautan::class),
                SelectFilter::make('jenis_kepemilikan')->label('Kepemilikan')->options(JenisKepemilikan::class),
                SelectFilter::make('unit_id')->label('Unit')->options(fn (): array => self::opsiUnit())->searchable(),
                SelectFilter::make('status_cek_tujuan')->label('Cek tujuan')->options(StatusCekTujuan::class),
                Filter::make('kedaluwarsa_7_hari')->label('Kedaluwarsa dalam 7 hari')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('aktif_sampai', [now(), now()->addDays(7)])),
            ])
            ->recordActions([
                Action::make('qr')->label('QR')->icon(Heroicon::OutlinedQrCode)->iconButton()
                    ->url(fn (TautanPendek $r): string => route('tautan.qr', ['tautan' => $r, 'format' => 'png']))->openUrlInNewTab(),
                EditAction::make()->visible(fn (TautanPendek $r): bool => auth()->user()?->can('update', $r) ?? false),
                ActionGroup::make(AksiStatus::semua())->label('Status'),
            ])
            ->toolbarActions([
                BulkAction::make('pindahkan_terpilih')->label('Pindahkan kepemilikan (admin)')->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->visible(fn (): bool => auth()->user()?->adalahAdmin() ?? false)
                    ->deselectRecordsAfterCompletion()
                    ->schema([
                        Select::make('unit_tujuan')->label('Pindahkan ke unit')->options(fn (): array => Unit::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')->all())->searchable(),
                        Select::make('pengguna_tujuan')->label('...atau ke pengguna')->options(fn (): array => User::query()->where('aktif', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $ke = filled($data['unit_tujuan'] ?? null) ? Unit::find($data['unit_tujuan']) : (filled($data['pengguna_tujuan'] ?? null) ? User::find($data['pengguna_tujuan']) : null);
                        if ($ke === null) {
                            Notification::make()->title('Pilih unit atau pengguna tujuan')->danger()->send();

                            return;
                        }

                        $berhasil = 0;
                        foreach ($records as $tautan) {
                            try {
                                app(PindahkanKepemilikanTautan::class)->jalankan($tautan, $ke, auth()->user(), 'Pemindahan massal oleh admin');
                                $berhasil++;
                            } catch (ValidationException) {
                                // dilewati: tujuan sama dengan pemilik saat ini
                            }
                        }
                        Notification::make()->title("{$berhasil} tautan dipindahkan")->success()->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [RiwayatStatusRelationManager::class, RiwayatKepemilikanRelationManager::class];
    }

    /** Item navigasi tambahan: antrean persetujuan slug (tautan.setujui). */
    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
            NavigationItem::make('Antrean persetujuan')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->sort(2)
                ->url(fn (): string => static::getUrl('persetujuan'))
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.persetujuan'))
                ->visible(fn (): bool => auth()->user()?->can(Izin::TautanSetujui->value) ?? false),
        ];
    }

    public static function getPages(): array
    {
        return [
            'persetujuan' => AntreanPersetujuan::route('/persetujuan'),
            'index' => ListTautanPendek::route('/'),
            'create' => CreateTautanPendek::route('/create'),
            'edit' => EditTautanPendek::route('/{record}/edit'),
        ];
    }
}
