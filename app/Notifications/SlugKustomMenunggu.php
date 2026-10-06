<?php

namespace App\Notifications;

use App\Models\TautanPendek;

class SlugKustomMenunggu extends NotifikasiAlias
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
        return $this->pesanDatabase(
            'Slug kustom menunggu persetujuan',
            "Slug \"{$this->tautan->kode}\" ({$this->tautan->judul}) menunggu keputusan.",
            'warning',
            url('/panel/tautan/persetujuan'),
        );
    }
}
