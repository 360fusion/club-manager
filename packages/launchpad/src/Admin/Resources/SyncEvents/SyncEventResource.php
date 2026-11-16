<?php

namespace Launchpad\Admin\Resources\SyncEvents;

use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\SyncEvent;

/** What the app has told the other service, and what it could not: a failed one is tried again from here once the service is back. */
class SyncEventResource extends Resource
{
    protected static ?string $model = SyncEvent::class;

    protected static ?string $slug = 'sync-events';

    protected static ?string $modelLabel = 'sync event';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function canViewAny(): bool
    {
        return config('launchpad.modules.sync') && StaffAccess::allowed('view-logs');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('launchpad.modules.sync');
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = SyncEvent::where('status', 'failed')->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Written')->dateTime('j M Y H:i:s')->sortable(),
                TextColumn::make('type')->badge()->searchable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => ['delivered' => 'success', 'failed' => 'danger'][$state] ?? 'warning'),
                TextColumn::make('attempts'),
                TextColumn::make('last_error')->wrap()->limit(120)->placeholder(''),
                TextColumn::make('event_id')->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('status')->options(['pending' => 'Waiting', 'delivered' => 'Delivered', 'failed' => 'Gave up'])])
            ->recordActions([
                Action::make('retry')->label('Try again')->visible(fn (SyncEvent $record): bool => $record->status === 'failed')
                    ->action(fn (SyncEvent $record) => $record->update(['status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'last_error' => null])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSyncEvents::route('/')];
    }
}
