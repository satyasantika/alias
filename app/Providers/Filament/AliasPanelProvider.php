<?php

namespace App\Providers\Filament;

use App\Enums\Izin;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dasbor;
use App\Http\Middleware\TolakAkunTerkunci;
use App\Http\Middleware\WajibMfaAdmin;
use App\Support\Filament\AppAuthenticationQrFix;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AliasPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('alias')
            ->path('panel')
            ->domain(config('alias.domain_panel') ?: null)
            ->login(Login::class)
            ->passwordReset()
            ->profile()
            ->databaseNotifications()
            ->brandName('ALIAS')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->darkMode(condition: true, isForced: false)
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => Blade::render('<div class="py-2 text-center text-xs text-gray-500">ALIAS v{{ config(\'app.versi\') }}</div>'),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => config('alias.login_google')
                    ? Blade::render('<div class="mt-4 text-center"><a class="fi-btn fi-color-gray inline-block rounded-lg px-4 py-2 text-sm ring-1 ring-gray-300" href="{{ route(\'auth.google.arahkan\') }}">Masuk dengan Google</a></div>')
                    : '',
            )
            ->authenticatedRoutes(function (): void {
                // Panduan super-admin: tidak berupa berkas publik; hanya pemegang pengaturan.kelola (super admin) berMFA.
                Route::get('panduan-super-admin', function () {
                    abort_unless(auth()->user()?->can(Izin::PengaturanKelola->value), 403);

                    return response(file_get_contents(resource_path('panduan/super-admin.html')), 200, [
                        'Content-Type' => 'text/html; charset=UTF-8',
                        'X-Robots-Tag' => 'noindex, nofollow',
                        'Cache-Control' => 'no-store, private',
                    ]);
                })->name('panduan-super-admin');
            })
            ->navigationItems([
                NavigationItem::make('Panduan super admin')
                    ->group('Sistem')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->sort(99)
                    ->url(fn (): string => url('/panel/panduan-super-admin'), shouldOpenInNewTab: true)
                    ->visible(fn (): bool => auth()->user()?->can(Izin::PengaturanKelola->value) ?? false),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dasbor::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                TolakAkunTerkunci::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                WajibMfaAdmin::class,
            ]);

        // Sakelar sementara (alias.mfa_aktif): tanpa ini panel tidak menawarkan maupun menantang MFA.
        if (config('alias.mfa_aktif')) {
            $panel->multiFactorAuthentication([
                AppAuthenticationQrFix::make()->brandName('Alias FKIP')->recoverable(),
            ]);
        }

        return $panel;
    }
}
