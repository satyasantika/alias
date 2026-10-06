<?php

namespace App\Console\Commands;

use App\Models\LogLogin;
use App\Support\Pengaturan;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'alias:pangkas-log-login', description: 'Hapus log login yang melewati retensi (bawaan 90 hari)')]
class PangkasLogLogin extends Command
{
    public function handle(): int
    {
        $hari = (int) Pengaturan::ambil('retensi_log_login_hari', 90);
        $dihapus = LogLogin::query()->where('created_at', '<', now()->subDays($hari))->delete();

        $this->info("{$dihapus} log login dihapus.");

        return self::SUCCESS;
    }
}
