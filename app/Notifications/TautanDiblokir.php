<?php

namespace App\Notifications;

use App\Models\TautanPendek;
use Illuminate\Notifications\Messages\MailMessage;

class TautanDiblokir extends NotifikasiAlias
{
    public function __construct(public readonly TautanPendek $tautan, public readonly ?string $alasan = null)
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
        return "Tautan \"{$this->tautan->kode}\" ({$this->tautan->judul}) diblokir karena melanggar ketentuan. Alasan: ".($this->alasan ?: '-');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->pesanDatabase('Tautan diblokir', $this->isi(), 'danger', $this->urlTautan($this->tautan->getKey()));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Tautan Alias FKIP diblokir')->greeting('Halo '.$notifiable->name.',')
            ->line($this->isi())->line('Hubungi admin Alias bila Anda merasa ini keliru.');
    }
}
