<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AkunTerkunci extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $ip, public readonly int $menit)
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
        return (new MailMessage)
            ->subject('Akun Alias FKIP Anda dikunci sementara')
            ->greeting('Halo '.$notifiable->name.',')
            ->line("Akun Anda dikunci selama {$this->menit} menit karena terlalu banyak percobaan masuk yang gagal (alamat IP: {$this->ip}).")
            ->line('Bila itu bukan Anda, segera atur ulang kata sandi setelah kunci berakhir atau hubungi admin Alias.');
    }
}
