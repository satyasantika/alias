<?php

namespace App\Support\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use SensitiveParameter;

/**
 * Tambalan BR-34: pragmarx/google2fa-qrcode v4 (lihat Bacon.php, merge PR
 * antonioribeiro/google2fa-qrcode#27) sudah membungkus hasilnya sendiri menjadi
 * data URI (data:image/svg+xml;base64,... atau data:image/png;base64,...).
 * Filament 5.9 belum menyesuaikan: fallback-nya di generateQrCodeDataUri()
 * membungkus ulang hasil itu saat ekstensi imagick tidak tersedia, sehingga
 * terjadi base64 ganda dan QR gagal dirender browser. Lewati pembungkusan ulang.
 */
class AppAuthenticationQrFix extends AppAuthentication
{
    public function generateQrCodeDataUri(#[SensitiveParameter] string $secret): string
    {
        $user = $this->getHolderName(Filament::auth()->user());

        return $this->google2FA->getQRCodeInline(
            $this->getBrandName(),
            $user,
            $secret,
        );
    }
}
