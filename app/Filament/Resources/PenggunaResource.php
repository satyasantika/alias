<?php

namespace App\Filament\Resources;

use App\Actions\Pengguna\NonaktifkanPengguna;
use App\Enums\Izin;
use App\Enums\Peran;
use App\Filament\Resources\PenggunaResource\Pages\CreatePengguna;
use App\Filament\Resources\PenggunaResource\Pages\EditPengguna;
use App\Filament\Resources\PenggunaResource\Pages\ListPengguna;
use App\Models\Unit;
use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use STS\FilamentImpersonate\Actions\Impersonate;

class PenggunaResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    protected static ?string $slug = 'pengguna';

    protected static ?string $recordTitleAttribute = 'name';

    /** Admin melihat semua; pengelola unit hanya anggota unit yang ia kelola. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['roles']);
        $user = auth()->user();

        if ($user === null || $user->can(Izin::PenggunaKelola->value)) {
            return $query;
        }

        return $query->whereHas('unitAnggota', fn (Builder $q) => $q->whereIn('unit.id', $user->unitDikelola()->select('unit.id')));
    }

    /** @return array<string, string> */
    public static function opsiPeran(): array
    {
        $bolehAdmin = auth()->user()?->can(Izin::PenggunaAturPeranAdmin->value) ?? false;

        return collect(Peran::cases())
            ->filter(fn (Peran $p) => $bolehAdmin || ! $p->peranAdmin())
            ->mapWithKeys(fn (Peran $p) => [$p->value => $p->getLabel()])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun')->schema([
                TextInput::make('name')->label('Nama lengkap')->required()->maxLength(255),
                TextInput::make('email')->label('Surel')->email()->required()->maxLength(255)
                    ->rules([new SurelDomainUnsil])
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtolower(trim($state)))
                    ->placeholder('nama@unsil.ac.id'),
                TextInput::make('nip')->label('NIP')->maxLength(30),
                TextInput::make('nidn')->label('NIDN')->maxLength(20),
                Select::make('unit_id')->label('Unit homebase')
                    ->options(fn (): array => Unit::query()->orderBy('kode')->pluck('nama', 'id')->all())
                    ->searchable(),
                TextInput::make('kuota_tautan')->label('Kuota tautan')->numeric()->minValue(0)
                    ->helperText('Kosong = pakai kuota bawaan pengguna.'),
                Toggle::make('aktif')->label('Aktif')->default(true),
            ])->columns(2),
            Section::make('Peran')->schema([
                Select::make('peran')->label('Peran')->options(fn (): array => self::opsiPeran())
                    ->multiple()->required()
                    ->helperText('Peran admin hanya dapat diberikan oleh super admin.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->description(fn (User $r): string => $r->email),
                TextColumn::make('roles.name')->label('Peran')->badge()->separator(','),
                TextColumn::make('nip')->label('NIP')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('kuota_tautan')->label('Kuota')->placeholder('bawaan')->toggleable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
                TextColumn::make('terakhir_masuk_pada')->label('Terakhir masuk')->dateTime('d F Y H:i')->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('peran')->label('Peran')->options(fn (): array => self::opsiPeran())
                    ->query(fn (Builder $q, array $data): Builder => $q->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string $v) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', $v)),
                    )),
                TernaryFilter::make('aktif')->label('Status aktif'),
            ])
            ->recordActions([
                Impersonate::make()->iconButton()->tooltip('Masuk sebagai pengguna ini')
                    ->redirectTo(fn (): string => filament()->getUrl()),
                EditAction::make(),
                self::aksiNonaktifkan(),
                Action::make('aktifkan')->label('Aktifkan')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (User $r): bool => ! $r->aktif && (auth()->user()?->can('update', $r) ?? false))
                    ->requiresConfirmation()
                    ->action(fn (User $r) => $r->forceFill(['aktif' => true])->save()),
            ]);
    }

    private static function aksiNonaktifkan(): Action
    {
        return Action::make('nonaktifkan')
            ->label('Nonaktifkan')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (User $r): bool => $r->aktif && (auth()->user()?->can('update', $r) ?? false))
            ->modalDescription(fn (User $r): string => "Akun ini memiliki {$r->jumlahTautanAktif()} tautan aktif. Tautan tidak berhenti berfungsi, tetapi sebaiknya dialihkan agar tidak yatim.")
            ->schema([
                Placeholder::make('info')->label('')->content('Pilih tujuan pengalihan (opsional).'),
                Select::make('unit_tujuan')->label('Alihkan semua tautan ke unit')
                    ->options(fn (): array => Unit::query()->orderBy('kode')->pluck('nama', 'id')->all())->searchable(),
                Select::make('pengguna_tujuan')->label('...atau ke pengguna')
                    ->options(fn (): array => User::query()->where('aktif', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            ])
            ->action(function (User $record, array $data): void {
                $tujuan = filled($data['unit_tujuan'] ?? null)
                    ? Unit::find($data['unit_tujuan'])
                    : (filled($data['pengguna_tujuan'] ?? null) ? User::find($data['pengguna_tujuan']) : null);

                try {
                    app(NonaktifkanPengguna::class)->jalankan($record, auth()->user(), $tujuan);
                    Notification::make()->title('Akun dinonaktifkan')->success()->send();
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengguna::route('/'),
            'create' => CreatePengguna::route('/create'),
            'edit' => EditPengguna::route('/{record}/edit'),
        ];
    }
}
