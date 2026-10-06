<?php

namespace App\Filament\Resources;

use App\Actions\Akses\SetujuiPermintaanAkses;
use App\Actions\Akses\TolakPermintaanAkses;
use App\Enums\Peran;
use App\Enums\StatusPermintaanAkses;
use App\Filament\Resources\PermintaanAksesResource\Pages\ListPermintaanAkses;
use App\Models\PermintaanAkses;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PermintaanAksesResource extends Resource
{
    protected static ?string $model = PermintaanAkses::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?string $modelLabel = 'permintaan akses';

    protected static ?string $pluralModelLabel = 'permintaan akses';

    protected static ?string $slug = 'permintaan-akses';

    public static function getNavigationBadge(): ?string
    {
        $jumlah = PermintaanAkses::query()->where('status', StatusPermintaanAkses::Menunggu)->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('unit'))
            ->columns([
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d F Y H:i')->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->description(fn (PermintaanAkses $r): string => $r->email),
                TextColumn::make('unit.nama')->label('Unit')->placeholder('-'),
                TextColumn::make('alasan')->label('Keperluan')->limit(60)->tooltip(fn (PermintaanAkses $r): string => $r->alasan),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(StatusPermintaanAkses::class)
                    ->default(StatusPermintaanAkses::Menunggu->value),
            ])
            ->recordActions([
                Action::make('setujui')
                    ->label('Setujui')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (PermintaanAkses $r): bool => $r->status === StatusPermintaanAkses::Menunggu)
                    ->schema([
                        Select::make('peran')->label('Peran')->required()->default(Peran::Pengguna->value)
                            ->options([
                                Peran::Pengguna->value => Peran::Pengguna->getLabel(),
                                Peran::PengelolaUnit->value => Peran::PengelolaUnit->getLabel(),
                                Peran::Pemantau->value => Peran::Pemantau->getLabel(),
                            ]),
                        Select::make('unit_id')->label('Unit')->searchable()
                            ->options(fn (): array => Unit::query()->orderBy('nama')->pluck('nama', 'id')->all())
                            ->default(fn (PermintaanAkses $r) => $r->unit_id),
                    ])
                    ->action(function (PermintaanAkses $record, array $data): void {
                        try {
                            app(SetujuiPermintaanAkses::class)->jalankan(
                                $record, Peran::from($data['peran']), filled($data['unit_id'] ?? null) ? Unit::find($data['unit_id']) : null, auth()->user(),
                            );
                            Notification::make()->title('Akun dibuat dan surel atur kata sandi dikirim')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
                Action::make('tolak')
                    ->label('Tolak')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (PermintaanAkses $r): bool => $r->status === StatusPermintaanAkses::Menunggu)
                    ->schema([Textarea::make('alasan')->label('Alasan penolakan')->required()->minLength(5)->maxLength(1000)])
                    ->action(function (PermintaanAkses $record, array $data): void {
                        try {
                            app(TolakPermintaanAkses::class)->jalankan($record, $data['alasan'], auth()->user());
                            Notification::make()->title('Permintaan ditolak')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermintaanAkses::route('/'),
        ];
    }
}
