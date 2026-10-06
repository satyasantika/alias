<?php

namespace App\Filament\Resources\TautanPendekResource;

use App\Actions\Tautan\AktifkanTautan;
use App\Actions\Tautan\BlokirTautan;
use App\Actions\Tautan\BukaBlokirTautan;
use App\Actions\Tautan\HapusTautan;
use App\Actions\Tautan\NonaktifkanTautan;
use App\Actions\Tautan\PindahkanKepemilikanTautan;
use App\Actions\Tautan\SetujuiSlugKustom;
use App\Actions\Tautan\TolakSlugKustom;
use App\Actions\Tautan\UbahStatusTautan;
use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/** Aksi Filament transisi status; dipakai di tabel dan halaman edit. */
class AksiStatus
{
    /** @return list<Action> */
    public static function semua(): array
    {
        return [
            self::setujui(), self::tolak(), self::nonaktifkan(), self::aktifkan(), self::blokir(), self::bukaBlokir(), self::pindahkan(), self::hapus(),
        ];
    }

    private static function pindahkan(): Action
    {
        return Action::make('pindahkan')->label('Pindahkan kepemilikan')->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn (TautanPendek $r): bool => auth()->user()?->can('transfer', $r) ?? false)
            ->schema(function (TautanPendek $record): array {
                $tujuan = app(PindahkanKepemilikanTautan::class)->tujuanSah($record, auth()->user());

                return [
                    Select::make('unit_tujuan')->label('Pindahkan ke unit')->options($tujuan['unit'])->searchable()->live()
                        ->disabled(fn (Get $get): bool => filled($get('pengguna_tujuan'))),
                    Select::make('pengguna_tujuan')->label('...atau ke pengguna')->options($tujuan['pengguna'])->searchable()->live()
                        ->disabled(fn (Get $get): bool => filled($get('unit_tujuan'))),
                    Textarea::make('alasan')->label('Alasan')->maxLength(500)->placeholder('Mis. pegawai pindah tugas'),
                ];
            })
            ->action(function (TautanPendek $record, array $data): void {
                $ke = filled($data['unit_tujuan'] ?? null) ? Unit::find($data['unit_tujuan']) : (filled($data['pengguna_tujuan'] ?? null) ? User::find($data['pengguna_tujuan']) : null);

                if ($ke === null) {
                    Notification::make()->title('Pilih unit atau pengguna tujuan')->danger()->send();

                    return;
                }

                self::jalankan(fn () => app(PindahkanKepemilikanTautan::class)->jalankan($record, $ke, auth()->user(), $data['alasan'] ?? null), 'Kepemilikan dipindahkan');
            });
    }

    private static function alasan(bool $wajib = true): Textarea
    {
        return Textarea::make('alasan')->label('Alasan')->required($wajib)->minLength($wajib ? UbahStatusTautan::ALASAN_MINIMAL : null)->maxLength(500);
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

    private static function bila(string $kemampuan, StatusTautan ...$status): Closure
    {
        return fn (TautanPendek $r): bool => in_array($r->status, $status, true) && (auth()->user()?->can($kemampuan, $r) ?? false);
    }

    private static function setujui(): Action
    {
        return Action::make('setujui')->label('Setujui slug')->icon(Heroicon::OutlinedCheck)->color('success')->requiresConfirmation()
            ->visible(self::bila('setujui', StatusTautan::MenungguPersetujuan))
            ->action(fn (TautanPendek $record) => self::jalankan(fn () => app(SetujuiSlugKustom::class)->jalankan($record, auth()->user()), 'Slug disetujui'));
    }

    private static function tolak(): Action
    {
        return Action::make('tolak')->label('Tolak slug')->icon(Heroicon::OutlinedXMark)->color('danger')
            ->visible(self::bila('setujui', StatusTautan::MenungguPersetujuan))
            ->schema([self::alasan()])
            ->action(fn (TautanPendek $record, array $data) => self::jalankan(fn () => app(TolakSlugKustom::class)->jalankan($record, $data['alasan'], auth()->user()), 'Slug ditolak'));
    }

    private static function nonaktifkan(): Action
    {
        return Action::make('nonaktifkan')->label('Nonaktifkan')->icon(Heroicon::OutlinedPause)->color('gray')
            ->visible(self::bila('nonaktifkan', StatusTautan::Aktif))
            ->schema(fn (TautanPendek $record): array => [self::alasan(! UbahStatusTautan::pemilikTindakan($record, auth()->user()))])
            ->action(fn (TautanPendek $record, array $data) => self::jalankan(fn () => app(NonaktifkanTautan::class)->jalankan($record, auth()->user(), $data['alasan'] ?? null), 'Tautan dinonaktifkan'));
    }

    private static function aktifkan(): Action
    {
        return Action::make('aktifkan')->label('Aktifkan')->icon(Heroicon::OutlinedPlay)->color('success')->requiresConfirmation()
            ->visible(self::bila('nonaktifkan', StatusTautan::Dinonaktifkan))
            ->action(fn (TautanPendek $record) => self::jalankan(fn () => app(AktifkanTautan::class)->jalankan($record, auth()->user()), 'Tautan diaktifkan'));
    }

    private static function blokir(): Action
    {
        return Action::make('blokir')->label('Blokir')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
            ->visible(self::bila('blokir', StatusTautan::Aktif, StatusTautan::Dinonaktifkan))
            ->schema([self::alasan()])
            ->action(fn (TautanPendek $record, array $data) => self::jalankan(fn () => app(BlokirTautan::class)->jalankan($record, $data['alasan'], auth()->user()), 'Tautan diblokir'));
    }

    private static function bukaBlokir(): Action
    {
        return Action::make('buka_blokir')->label('Buka blokir')->icon(Heroicon::OutlinedLockOpen)->color('success')
            ->visible(self::bila('blokir', StatusTautan::Diblokir))
            ->schema([self::alasan(false)])
            ->action(fn (TautanPendek $record, array $data) => self::jalankan(fn () => app(BukaBlokirTautan::class)->jalankan($record, auth()->user(), $data['alasan'] ?? null), 'Blokir dibuka'));
    }

    private static function hapus(): Action
    {
        return Action::make('hapus')->label('Hapus')->icon(Heroicon::OutlinedTrash)->color('danger')->requiresConfirmation()
            ->modalDescription(fn (TautanPendek $r): string => $r->pertama_aktif_pada === null
                ? 'Tautan ini belum pernah aktif sehingga dihapus permanen dan slugnya bebas dipakai kembali.'
                : 'Kode tautan ini tetap terkunci selamanya agar QR/poster lama tidak mengarah ke tujuan lain.')
            ->visible(fn (TautanPendek $r): bool => auth()->user()?->can('delete', $r) ?? false)
            ->action(fn (TautanPendek $record) => self::jalankan(fn () => app(HapusTautan::class)->jalankan($record, auth()->user()), 'Tautan dihapus'));
    }
}
