<?php

namespace App\Actions\Tautan;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Stub: diisi pada F4.6 setelah tabel tautan_pendek tersedia. */
class PindahkanSemuaTautanPengguna
{
    public function jalankan(User $dari, User|Unit|Model $ke, User $oleh): int
    {
        return 0;
    }
}
