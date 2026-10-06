<?php

namespace App\Notifications;

use App\Models\TautanPendek;

class TautanAkanKedaluwarsa extends NotifikasiAlias
{
    public function __construct(public readonly TautanPendek $tautan)
    {
        parent::__construct();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $kapan = $this->tautan->aktif_sampai?->translatedFormat('d F Y H:i') ?? '-';

        return $this->pesanDatabase(
            'Tautan akan kedaluwarsa',
            "Tautan \"{$this->tautan->kode}\" ({$this->tautan->judul}) kedaluwarsa pada {$kapan}.",
            'warning',
            $this->urlTautan($this->tautan->getKey()),
        );
    }
}
