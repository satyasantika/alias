<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as NotifikasiFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Dasar notifikasi Alias: berantrean (`notifikasi`), format database Filament agar tampil di lonceng panel. */
abstract class NotifikasiAlias extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct()
    {
        $this->onQueue('notifikasi');
        $this->afterCommit();
    }

    /**
     * @return array<string, mixed>
     */
    protected function pesanDatabase(string $judul, string $isi, string $status = 'info', ?string $url = null): array
    {
        $notifikasi = NotifikasiFilament::make()->title($judul)->body($isi)->status($status);

        if ($url !== null) {
            $notifikasi->actions([Action::make('lihat')->label('Lihat')->url($url)->markAsRead()]);
        }

        return $notifikasi->getDatabaseMessage();
    }

    protected function urlTautan(string $id): string
    {
        return url('/panel/tautan/'.$id.'/edit');
    }
}
