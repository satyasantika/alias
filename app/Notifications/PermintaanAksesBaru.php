<?php

namespace App\Notifications;

use App\Models\PermintaanAkses;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PermintaanAksesBaru extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PermintaanAkses $permintaan)
    {
        $this->onQueue('notifikasi');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'format' => 'filament',
            'title' => 'Permintaan akses baru',
            'body' => $this->permintaan->nama.' ('.$this->permintaan->email.') meminta akses.',
            'status' => 'info',
            'duration' => 'persistent',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Permintaan akses baru: '.$this->permintaan->nama)
            ->line($this->permintaan->nama.' ('.$this->permintaan->email.') meminta akses ke Alias FKIP.')
            ->line('Alasan: '.$this->permintaan->alasan)
            ->action('Tinjau permintaan', url('/panel/permintaan-akses'));
    }
}
