<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use App\Notifications\AturKataSandiAkun;
use App\Rules\SurelDomainUnsil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** BR-31: akun dibuat admin tanpa kata sandi; pemilik mengaturnya lewat tautan bertanda tangan. */
class BuatPengguna
{
    /**
     * @param  array{name: string, email: string, nip?: ?string, nidn?: ?string, unit_id?: ?string, kuota_tautan?: ?int, aktif?: bool, peran?: list<string>}  $data
     */
    public function jalankan(array $data, User $oleh): User
    {
        Gate::forUser($oleh)->authorize('create', User::class);

        $data['email'] = Str::lower(trim($data['email']));
        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new SurelDomainUnsil, 'unique:users,email'],
        ])->validate();

        $user = DB::transaction(function () use ($data, $oleh): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'nip' => $data['nip'] ?? null,
                'nidn' => $data['nidn'] ?? null,
                'unit_id' => $data['unit_id'] ?? null,
                'kuota_tautan' => $data['kuota_tautan'] ?? null,
                'aktif' => $data['aktif'] ?? true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            app(AturPeranPengguna::class)->jalankan($user, $data['peran'] ?? [], $oleh);

            return $user;
        });

        $user->notify(new AturKataSandiAkun(Password::broker()->createToken($user)));

        return $user;
    }
}
