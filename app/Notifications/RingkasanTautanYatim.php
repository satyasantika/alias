<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class RingkasanTautanYatim extends NotifikasiAlias
{
    /** @param  list<array{kode: string, judul: string, pemilik: string}>  $daftar */
    public function __construct(public readonly array $daftar, public readonly int $total)
    {
        parent::__construct();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $surel = (new MailMessage)->subject("Laporan mingguan: {$this->total} tautan yatim")->greeting('Halo '.$notifiable->name.',')
            ->line("Terdapat {$this->total} tautan aktif yang pemiliknya sudah tidak aktif. Sebaiknya dialihkan ke unit atau pengguna lain.");

        foreach ($this->daftar as $baris) {
            $surel->line("• {$baris['kode']} — {$baris['judul']} (pemilik: {$baris['pemilik']})");
        }

        if ($this->total > count($this->daftar)) {
            $surel->line('...dan '.($this->total - count($this->daftar)).' lainnya.');
        }

        return $surel->action('Buka daftar tautan', url('/panel/tautan'));
    }
}
