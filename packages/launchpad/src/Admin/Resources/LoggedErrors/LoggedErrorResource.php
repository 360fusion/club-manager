<?php

namespace Launchpad\Admin\Resources\LoggedErrors;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\LoggedError;

/** The errors the app reported, one row per place, newest first. Staff mark one dealt with; it opens again if it happens again. */
class LoggedErrorResource extends Resource
{
    protected static ?string $model = LoggedError::class;

    protected static ?string $slug = 'errors';

    protected static ?string $modelLabel = 'error';

    protected static ?string $pluralModelLabel = 'Errors';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function canViewAny(): bool
    {
        return StaffAccess::allowed('view-logs');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationBadge(): ?string
    {
        $open = LoggedError::whereNull('resolved_at')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('class'),
                TextEntry::make('place')->getStateUsing(fn (LoggedError $record) => "{$record->file}:{$record->line}"),
                TextEntry::make('occurrences')->label('Times'),
                TextEntry::make('first_seen_at')->label('First seen')->dateTime('j M Y H:i'),
                TextEntry::make('last_seen_at')->label('Last seen')->dateTime('j M Y H:i'),
                TextEntry::make('resolved_at')->label('Dealt with')->dateTime('j M Y H:i')->placeholder('Not yet'),
                TextEntry::make('message')->columnSpanFull(),
                TextEntry::make('request')->label('Last request')->getStateUsing(fn (LoggedError $record) => $record->url ? "{$record->method} {$record->url}" : null)->placeholder('A command or a job')->columnSpanFull(),
                TextEntry::make('user_id')->label('Signed in as (user id)')->placeholder('Nobody'),
            ])->columns(3),
            Section::make('Trace')->collapsed()->schema([TextEntry::make('trace')->hiddenLabel()->fontFamily('mono')->extraAttributes(['style' => 'white-space: pre-wrap'])]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('last_seen_at')->label('Last seen')->since()->sortable(),
                TextColumn::make('class')->formatStateUsing(fn (string $state) => class_basename($state))->searchable(),
                TextColumn::make('message')->limit(80)->searchable(),
                TextColumn::make('occurrences')->label('Times')->sortable(),
                TextColumn::make('resolved_at')->label('Status')->badge()->getStateUsing(fn (LoggedError $record) => $record->resolved_at ? 'Dealt with' : 'Open')
                    ->color(fn (string $state) => $state === 'Open' ? 'danger' : 'gray'),
            ])
            ->filters([TernaryFilter::make('resolved_at')->label('Dealt with')->nullable()->default(false)])
            ->recordActions([
                ViewAction::make(),
                Action::make('resolve')->label('Dealt with')->visible(fn (LoggedError $record) => $record->resolved_at === null)
                    ->action(fn (LoggedError $record) => $record->update(['resolved_at' => now()])),
                Action::make('reopen')->label('Open again')->visible(fn (LoggedError $record) => $record->resolved_at !== null)
                    ->action(fn (LoggedError $record) => $record->update(['resolved_at' => null])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageLoggedErrors::route('/')];
    }
}
