<?php

namespace App\Notifications;

use App\Models\TautanPendek;
use Illuminate\Notifications\Messages\MailMessage;

class SlugKustomDiputuskan extends NotifikasiAlias
{
    public function __construct(public readonly TautanPendek $tautan, public readonly bool $disetujui, public readonly ?string $alasan = null)
    {
        parent::__construct();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function judul(): string
    {
        return $this->disetujui ? 'Slug kustom disetujui' : 'Slug kustom ditolak';
    }

    private function isi(): string
    {
        return $this->disetujui
            ? "Slug \"{$this->tautan->kode}\" kini aktif dan dapat dipakai."
            : "Slug \"{$this->tautan->kode}\" ditolak. Alasan: ".($this->alasan ?: '-').' Anda dapat mengganti slug lalu mengajukan ulang.';
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->pesanDatabase($this->judul(), $this->isi(), $this->disetujui ? 'success' : 'danger', $this->urlTautan($this->tautan->getKey()));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->judul())->greeting('Halo '.$notifiable->name.',')
            ->line($this->isi())->action('Buka tautan', $this->urlTautan($this->tautan->getKey()));
    }
}
