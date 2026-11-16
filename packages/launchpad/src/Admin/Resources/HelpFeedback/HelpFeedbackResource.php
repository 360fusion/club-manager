<?php

namespace Launchpad\Admin\Resources\HelpFeedback;

use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\HelpFeedback;

/** The answers to "Was this helpful?", newest first. They say nothing about who answered. */
class HelpFeedbackResource extends Resource
{
    protected static ?string $model = HelpFeedback::class;

    protected static ?string $slug = 'help-feedback';

    protected static ?string $modelLabel = 'help feedback';

    protected static ?string $pluralModelLabel = 'Help feedback';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-hand-thumb-up';
    }

    public static function canViewAny(): bool
    {
        return StaffAccess::allowed('manage-messages');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y H:i')->sortable(),
                TextColumn::make('article')->searchable()->url(fn (HelpFeedback $record) => url('/help/'.$record->article), shouldOpenInNewTab: true),
                IconColumn::make('helpful')->boolean(),
                TextColumn::make('comment')->wrap()->limit(200)->placeholder(''),
                TextColumn::make('version')->label('Article version')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TernaryFilter::make('helpful')->label('Helpful')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageHelpFeedback::route('/')];
    }
}
