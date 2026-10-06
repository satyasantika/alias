<?php

namespace App\Notifications;

use App\Enums\StatusPermintaanAkses;
use App\Models\PermintaanAkses;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PermintaanAksesDiputuskan extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PermintaanAkses $permintaan)
    {
        $this->onQueue('notifikasi');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('Halo '.$this->permintaan->nama.',');

        if ($this->permintaan->status === StatusPermintaanAkses::Disetujui) {
            return $mail->subject('Permintaan akses Alias FKIP disetujui')
                ->line('Permintaan akses Anda disetujui. Periksa surel terpisah berisi tautan untuk mengatur kata sandi.');
        }

        return $mail->subject('Permintaan akses Alias FKIP ditolak')
            ->line('Mohon maaf, permintaan akses Anda belum dapat disetujui.')
            ->line('Alasan: '.($this->permintaan->catatan ?: '-'));
    }
}
