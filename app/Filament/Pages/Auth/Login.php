<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable
    {
        return 'Masuk ke Alias FKIP';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return new HtmlString(
            'Gunakan surel <strong>@unsil.ac.id</strong>. Belum punya akun? <a class="font-medium text-primary-600 hover:underline" href="'.e(url('/minta-akses')).'">Minta akses</a>.'
        );
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Surel')
            ->email()
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->placeholder('nama@unsil.ac.id');
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Kata sandi')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Surel atau kata sandi salah, atau akun tidak aktif.',
        ]);
    }
}
