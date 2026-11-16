<?php

namespace Launchpad\Admin\Resources\AdminActions;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\AdminAction;
use Launchpad\Models\AdminLogLabels;

/** What staff have done. Nothing here can be changed or deleted. */
class AdminActionResource extends Resource
{
    protected static ?string $model = AdminAction::class;

    protected static ?string $slug = 'admin-activity';

    protected static ?string $modelLabel = 'admin action';

    protected static ?string $pluralModelLabel = 'Admin activity';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function canViewAny(): bool
    {
        return StaffAccess::allowed('view-logs');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
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
                TextEntry::make('created_at')->label('When')->dateTime('j M Y H:i:s'),
                TextEntry::make('actor.email')->label('Who'),
                TextEntry::make('action')->formatStateUsing(fn (string $state) => AdminLogLabels::for($state)),
                TextEntry::make('summary'),
                TextEntry::make('ip')->label('From'),
            ])->columns(3),
            Section::make('Before')->schema([KeyValueEntry::make('before')->hiddenLabel()])->visible(fn (AdminAction $record): bool => filled($record->before)),
            Section::make('After')->schema([KeyValueEntry::make('after')->hiddenLabel()])->visible(fn (AdminAction $record): bool => filled($record->after)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('actor'))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y H:i:s')->sortable(),
                TextColumn::make('actor.email')->label('Who')->searchable(),
                TextColumn::make('action')->badge()->formatStateUsing(fn (string $state) => AdminLogLabels::for($state)),
                TextColumn::make('summary')->limit(80)->searchable(),
            ])
            ->filters([SelectFilter::make('action')->options(fn (): array => array_map(fn ($a) => AdminLogLabels::for($a), AdminAction::query()->distinct()->pluck('action', 'action')->all()))])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAdminActions::route('/')];
    }
}
