<?php

namespace App\Notifications;

use App\Models\TautanPendek;
use Illuminate\Notifications\Messages\MailMessage;

class TautanDibukaBlokir extends NotifikasiAlias
{
    public function __construct(public readonly TautanPendek $tautan)
    {
        parent::__construct();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function isi(): string
    {
        return "Blokir pada tautan \"{$this->tautan->kode}\" ({$this->tautan->judul}) telah dibuka; tautan aktif kembali.";
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->pesanDatabase('Blokir tautan dibuka', $this->isi(), 'success', $this->urlTautan($this->tautan->getKey()));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Blokir tautan Alias FKIP dibuka')->greeting('Halo '.$notifiable->name.',')->line($this->isi());
    }
}
