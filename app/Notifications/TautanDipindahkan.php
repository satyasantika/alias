<?php

namespace App\Notifications;

use App\Models\TautanPendek;

/** Satu tautan, atau ringkasan pemindahan massal ($jumlah > 1). */
class TautanDipindahkan extends NotifikasiAlias
{
    public function __construct(
        public readonly ?TautanPendek $tautan,
        public readonly string $dari,
        public readonly string $ke,
        public readonly int $jumlah = 1,
    ) {
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
        $isi = $this->jumlah > 1 || $this->tautan === null
            ? "{$this->jumlah} tautan dipindahkan dari {$this->dari} ke {$this->ke}."
            : "Tautan \"{$this->tautan->kode}\" ({$this->tautan->judul}) dipindahkan dari {$this->dari} ke {$this->ke}.";

        return $this->pesanDatabase('Kepemilikan tautan dipindahkan', $isi, 'info', $this->tautan !== null && $this->jumlah === 1 ? $this->urlTautan($this->tautan->getKey()) : url('/panel/tautan'));
    }
}
