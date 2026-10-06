<?php

namespace App\Actions\Akses;

use App\Enums\StatusPermintaanAkses;
use App\Models\PermintaanAkses;
use App\Models\User;
use App\Notifications\VerifikasiSurelPermintaanAkses;
use App\Rules\SurelDomainUnsil;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * BR-31/BR-32. Hasil yang sama (null) untuk surel baru, surel terdaftar, maupun permintaan terbuka:
 * tidak membocorkan keberadaan akun.
 */
class AjukanPermintaanAkses
{
    /**
     * @param  array{nama: string, email: string, nip?: ?string, unit_id?: ?string, alasan: string, peran_diminta?: ?string}  $data
     */
    public function jalankan(array $data, string $ipHash): ?PermintaanAkses
    {
        $data['email'] = Str::lower(trim($data['email']));

        Validator::make($data, [
            'nama' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', new SurelDomainUnsil],
            'nip' => ['nullable', 'string', 'max:30'],
            'unit_id' => ['nullable', 'exists:unit,id'],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ])->validate();

        $sudahTerdaftar = User::withTrashed()->where('email', $data['email'])->exists();
        $sudahTerbuka = PermintaanAkses::query()
            ->where('email', $data['email'])
            ->whereIn('status', [StatusPermintaanAkses::MenungguVerifikasiSurel, StatusPermintaanAkses::Menunggu])
            ->exists();

        if ($sudahTerdaftar || $sudahTerbuka) {
            return null;
        }

        $permintaan = PermintaanAkses::create([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'nip' => $data['nip'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'peran_diminta' => 'pengguna',
            'alasan' => $data['alasan'],
            'sumber' => 'formulir',
            'status' => StatusPermintaanAkses::MenungguVerifikasiSurel,
            'ip_hash' => $ipHash,
        ]);

        Notification::route('mail', $permintaan->email)->notify(new VerifikasiSurelPermintaanAkses($permintaan));

        return $permintaan;
    }
}
