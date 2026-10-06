<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogAktivitasResource\Pages\ListLogAktivitas;
use App\Models\Activity;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class LogAktivitasResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'log aktivitas';

    protected static ?string $pluralModelLabel = 'log aktivitas';

    protected static ?string $slug = 'log-aktivitas';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i')->sortable(),
                TextColumn::make('log_name')->label('Log')->badge()->color('gray'),
                TextColumn::make('event')->label('Peristiwa')->badge(),
                TextColumn::make('subject_type')
                    ->label('Subjek')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-')
                    ->description(fn (Activity $record): string => (string) $record->subject_id),
                TextColumn::make('causer.name')->label('Pelaku')->placeholder('Sistem'),
                TextColumn::make('perubahan')
                    ->label('Perubahan (lama → baru)')
                    ->state(fn (Activity $record): HtmlString => self::ringkasPerubahan($record))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Log')
                    ->options(fn (): array => Activity::query()->distinct()->pluck('log_name', 'log_name')->all()),
                SelectFilter::make('subject_type')
                    ->label('Subjek')
                    ->options(fn (): array => Activity::query()->whereNotNull('subject_type')->distinct()
                        ->pluck('subject_type')->mapWithKeys(fn (string $t) => [$t => class_basename($t)])->all()),
                SelectFilter::make('causer_id')
                    ->label('Pelaku')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Filter::make('rentang')
                    ->schema([
                        DatePicker::make('dari')->label('Dari'),
                        DatePicker::make('sampai')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function ringkasPerubahan(Activity $record): HtmlString
    {
        $baru = (array) ($record->attribute_changes['attributes'] ?? []);
        $lama = (array) ($record->attribute_changes['old'] ?? []);
        $baris = [];

        foreach ($baru as $kunci => $nilai) {
            $baris[] = e($kunci).': '.e(self::teks($lama[$kunci] ?? null)).' → '.e(self::teks($nilai));
        }

        return new HtmlString(implode('<br>', $baris));
    }

    private static function teks(mixed $nilai): string
    {
        return match (true) {
            $nilai === null => '∅',
            is_bool($nilai) => $nilai ? 'ya' : 'tidak',
            is_scalar($nilai) => (string) $nilai,
            default => (string) json_encode($nilai),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogAktivitas::route('/'),
        ];
    }
}
