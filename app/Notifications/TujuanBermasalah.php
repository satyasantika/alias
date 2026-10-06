<?php

namespace App\Notifications;

use App\Models\TautanPendek;
use Illuminate\Notifications\Messages\MailMessage;

class TujuanBermasalah extends NotifikasiAlias
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
        return "Tujuan tautan \"{$this->tautan->kode}\" ({$this->tautan->judul}) tidak dapat dibuka pada dua pemeriksaan berturut-turut. Periksa atau perbarui URL tujuan.";
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->pesanDatabase('Tujuan tautan bermasalah', $this->isi(), 'warning', $this->urlTautan($this->tautan->getKey()));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Tujuan tautan Alias FKIP bermasalah')->greeting('Halo '.$notifiable->name.',')
            ->line($this->isi())->action('Perbaiki tautan', $this->urlTautan($this->tautan->getKey()));
    }
}
