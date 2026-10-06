<?php

namespace App\Notifications;

use App\Models\PermintaanAkses;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifikasiSurelPermintaanAkses extends Notification implements ShouldQueue
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
        $url = URL::temporarySignedRoute('akses.verifikasi', now()->addHours(24), ['permintaan' => $this->permintaan->getKey()]);

        return (new MailMessage)
            ->subject('Verifikasi permintaan akses Alias FKIP')
            ->greeting('Halo '.$this->permintaan->nama.',')
            ->line('Kami menerima permintaan akses Alias FKIP dengan surel ini. Klik tombol di bawah untuk memverifikasi surel Anda.')
            ->action('Verifikasi surel', $url)
            ->line('Tautan berlaku 24 jam. Bila bukan Anda yang meminta, abaikan surel ini.');
    }
}
