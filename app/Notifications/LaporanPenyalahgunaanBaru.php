<?php

namespace App\Notifications;

use App\Models\LaporanPenyalahgunaan;
use Illuminate\Notifications\Messages\MailMessage;

class LaporanPenyalahgunaanBaru extends NotifikasiAlias
{
    public function __construct(public readonly LaporanPenyalahgunaan $laporan)
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
        return "Laporan {$this->laporan->kategori->getLabel()} untuk tautan \"{$this->laporan->kode_dilaporkan}\" menunggu tinjauan.";
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->pesanDatabase('Laporan penyalahgunaan baru', $this->isi(), 'danger', url('/panel/laporan-penyalahgunaan'));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Laporan penyalahgunaan baru: '.$this->laporan->kode_dilaporkan)->greeting('Halo '.$notifiable->name.',')
            ->line($this->isi())->action('Tinjau laporan', url('/panel/laporan-penyalahgunaan'));
    }
}
