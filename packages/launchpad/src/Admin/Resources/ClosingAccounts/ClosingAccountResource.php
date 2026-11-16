<?php

namespace Launchpad\Admin\Resources\ClosingAccounts;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Accounts\AccountClosure;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\StaffAccess;
use Launchpad\Launchpad;

/** Accounts that have been closed and wait out the retention period: who they are, when they go for good, and a way to put one back */
class ClosingAccountResource extends Resource
{
    protected static ?string $slug = 'closing-accounts';

    protected static ?string $modelLabel = 'closing account';

    public static function getModel(): string
    {
        return Launchpad::userModel();
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-minus';
    }

    public static function canViewAny(): bool
    {
        return StaffAccess::allowed('view-accounts');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Accounts';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('deletion_pending_at');
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
        $days = app(AccountClosure::class)->retentionDays();

        return $table
            ->defaultSort('deletion_pending_at')
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('deletion_pending_at')->label('Closed')->dateTime('j M Y H:i')->sortable(),
                TextColumn::make('removal')->label('Removed for good')->state(fn (Model $record) => $record->deletion_pending_at->addDays($days))->dateTime('j M Y')->description(fn (Model $record) => $record->deletion_pending_at->addDays($days)->diffForHumans()),
            ])
            ->recordActions([
                Action::make('restore')->label('Restore')->visible(fn (): bool => StaffAccess::allowed('close-accounts'))->requiresConfirmation()
                    ->modalDescription('The account can sign in again. What it paid for was ended and is not restarted.')
                    ->action(function (Model $record) {
                        app(AccountClosure::class)->restore($record, AdminLog::actor());
                        Notification::make()->success()->title('Account restored')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageClosingAccounts::route('/')];
    }
}
