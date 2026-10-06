<?php

namespace App\Filament\Resources\UnitResource\RelationManagers;

use App\Actions\Unit\KeluarkanAnggotaUnit;
use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\PeranUnit;
use App\Models\AnggotaUnit;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class AnggotaRelationManager extends RelationManager
{
    protected static string $relationship = 'keanggotaan';

    protected static ?string $title = 'Anggota & pengelola';

    /** Aksi tambah/keluarkan dijaga Policy (kelolaAnggota), sehingga tetap tampil pada halaman lihat. */
    public function isReadOnly(): bool
    {
        return false;
    }

    private function unit(): Unit
    {
        /** @var Unit */
        return $this->getOwnerRecord();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Nama')->searchable(),
                TextColumn::make('user.email')->label('Surel'),
                TextColumn::make('peran_unit')->label('Peran di unit')->badge(),
            ])
            ->headerActions([
                Action::make('tambah')
                    ->label('Tambah anggota')
                    ->visible(fn (): bool => auth()->user()?->can('kelolaAnggota', $this->unit()) ?? false)
                    ->schema([
                        Select::make('user_id')->label('Pengguna')->required()->searchable()
                            ->getSearchResultsUsing(fn (string $cari): array => User::query()->where('aktif', true)
                                ->where(fn ($q) => $q->where('name', 'like', "%{$cari}%")->orWhere('email', 'like', "%{$cari}%"))
                                ->limit(20)->pluck('name', 'id')->all()),
                        Select::make('peran_unit')->label('Peran di unit')->options(PeranUnit::class)->default(PeranUnit::Anggota->value)->required(),
                    ])
                    ->action(function (array $data): void {
                        try {
                            app(TambahAnggotaUnit::class)->jalankan(
                                $this->unit(), User::findOrFail($data['user_id']), PeranUnit::from($data['peran_unit']), auth()->user(),
                            );
                            Notification::make()->title('Anggota ditambahkan')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('keluarkan')
                    ->label('Keluarkan')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => auth()->user()?->can('kelolaAnggota', $this->unit()) ?? false)
                    ->action(function (AnggotaUnit $record): void {
                        try {
                            app(KeluarkanAnggotaUnit::class)->jalankan($this->unit(), $record->user, auth()->user());
                            Notification::make()->title('Anggota dikeluarkan')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ]);
    }
}
