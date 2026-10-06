<?php

namespace App\Filament\Pages;

use App\Enums\Izin;
use App\Models\Pengaturan as ModelPengaturan;
use App\Support\Pengaturan;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Nilai bisnis yang dapat diubah tanpa deploy (03 §4.15, §6.5). Hanya pemegang pengaturan.kelola (super-admin).
 *
 * @property-read Schema $form
 */
class PengaturanSistem extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $title = 'Pengaturan sistem';

    protected static ?string $navigationLabel = 'Pengaturan sistem';

    protected static ?string $slug = 'pengaturan-sistem';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Izin::PengaturanKelola->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::KUNCI)->mapWithKeys(fn (string $kunci) => [$kunci => Pengaturan::ambil($kunci)])->all());
    }

    /** @var list<string> */
    public const KUNCI = [
        'kuota_bawaan_pengguna', 'kuota_bawaan_unit', 'slug_kustom_perlu_persetujuan', 'mode_domain',
        'retensi_kunjungan_bulan', 'retensi_kunjungan_bot_hari', 'retensi_log_login_hari',
        'ambang_blokir_otomatis', 'hari_kedaluwarsa_persetujuan', 'hari_pengingat_kedaluwarsa', 'teks_pemberitahuan_privasi',
    ];

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Kuota')->schema([
                TextInput::make('kuota_bawaan_pengguna')->label('Kuota tautan pribadi bawaan')->numeric()->required()->integer()->minValue(1)->maxValue(100000),
                TextInput::make('kuota_bawaan_unit')->label('Kuota tautan unit bawaan')->numeric()->required()->integer()->minValue(1)->maxValue(1000000),
            ])->columns(2),
            Section::make('Persetujuan & domain tujuan')->schema([
                Toggle::make('slug_kustom_perlu_persetujuan')->label('Slug kustom pengguna perlu persetujuan'),
                Select::make('mode_domain')->label('Mode domain tujuan')->required()
                    ->options(['bebas' => 'Bebas (kecuali daftar blokir)', 'daftar_putih' => 'Daftar putih (hanya domain diizinkan)']),
            ])->columns(2),
            Section::make('Retensi data')->schema([
                TextInput::make('retensi_kunjungan_bulan')->label('Kunjungan manusia (bulan)')->numeric()->required()->integer()->minValue(1)->maxValue(60),
                TextInput::make('retensi_kunjungan_bot_hari')->label('Kunjungan bot (hari)')->numeric()->required()->integer()->minValue(1)->maxValue(365),
                TextInput::make('retensi_log_login_hari')->label('Log login (hari)')->numeric()->required()->integer()->minValue(7)->maxValue(365),
            ])->columns(3),
            Section::make('Moderasi & pengingat')->schema([
                TextInput::make('ambang_blokir_otomatis')->label('Ambang blokir otomatis (0 = nonaktif)')->numeric()->required()->integer()->minValue(0)->maxValue(50),
                TextInput::make('hari_kedaluwarsa_persetujuan')->label('Hari slug menunggu sebelum ditolak otomatis')->numeric()->required()->integer()->minValue(1)->maxValue(90),
                TextInput::make('hari_pengingat_kedaluwarsa')->label('Hari pengingat sebelum tautan kedaluwarsa')->numeric()->required()->integer()->minValue(1)->maxValue(90),
            ])->columns(3),
            Section::make('Halaman privasi')->schema([
                Textarea::make('teks_pemberitahuan_privasi')->label('Ringkasan pemberitahuan privasi')->rows(5)->maxLength(2000)->required()
                    ->helperText('Ditampilkan di /privasi sebagai teks polos.'),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('simpan')
                ->footer([Actions::make([Action::make('simpan')->label('Simpan pengaturan')->submit('simpan')])->key('aksi-form')]),
        ]);
    }

    public function simpan(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        foreach (self::KUNCI as $kunci) {
            if (array_key_exists($kunci, $data) && ModelPengaturan::query()->where('kunci', $kunci)->exists()) {
                Pengaturan::atur($kunci, $data[$kunci], auth()->id());
            }
        }

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
