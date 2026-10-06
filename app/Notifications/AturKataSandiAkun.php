<?php

namespace App\Notifications;

use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AturKataSandiAkun extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token)
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
        $url = Filament::getPanel('alias')->getResetPasswordUrl($this->token, $notifiable);

        return (new MailMessage)
            ->subject('Akun Alias FKIP Anda telah dibuat')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Akun Alias FKIP Anda sudah dibuat. Atur kata sandi untuk mulai memakainya.')
            ->action('Atur kata sandi', $url)
            ->line('Tautan ini berlaku 60 menit. Bila kedaluwarsa, gunakan "Lupa kata sandi" di halaman masuk.');
    }
}
