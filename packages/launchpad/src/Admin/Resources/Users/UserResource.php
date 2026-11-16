<?php

namespace Launchpad\Admin\Resources\Users;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\Impersonation;
use Launchpad\Admin\StaffAccess;
use Launchpad\Admin\UserSafeguards;
use Launchpad\Http\Controllers\Admin\ImpersonationController;
use Launchpad\Launchpad;

class UserResource extends Resource
{
    protected static ?string $slug = 'users';

    protected static ?string $recordTitleAttribute = 'email';

    public static function getModel(): string
    {
        return Launchpad::userModel();
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function canViewAny(): bool
    {
        return StaffAccess::allowed('view-accounts');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Accounts';
    }

    public static function canEdit(Model $record): bool
    {
        return StaffAccess::allowed('manage-accounts');
    }

    public static function canCreate(): bool
    {
        return false; // people make their own accounts
    }

    public static function canDelete(Model $record): bool
    {
        return false; // an account is closed (kept, then removed), never deleted from a button
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->label('Email address')
                    ->rules(fn (?Model $record): array => AccountRegistration::emailRules($record?->getKey()))
                    ->dehydrateStateUsing(fn ($state) => AccountRegistration::normalizeEmail($state)),
                Select::make('staff_role')->label('Staff role')
                    ->options(['admin' => 'Admin', 'support' => 'Support'])
                    ->placeholder('Customer (not staff)')
                    ->disabled(fn (?Model $record): bool => ! $record || ! UserSafeguards::canChangeStaffRole($record))
                    ->helperText('Admins can do everything; support can look and help. Set the details on the Staff page. You cannot change your own.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('staff_role')->label('Staff')->badge()->placeholder(''),
                IconColumn::make('email_verified_at')->label('Verified')->boolean()->getStateUsing(fn (Model $record): bool => $record->email_verified_at !== null),
                IconColumn::make('two_factor_confirmed_at')->label('2FA')->boolean()->getStateUsing(fn (Model $record): bool => $record->two_factor_confirmed_at !== null),
                TextColumn::make('deletion_pending_at')->label('Closed')->dateTime('j M Y')->placeholder('')->color('danger'),
                TextColumn::make('suspended_at')->label('Suspended')->dateTime('j M Y')->placeholder('')->color('warning'),
                TextColumn::make('created_at')->label('Joined')->dateTime('j M Y')->sortable(),
            ])
            ->filters([
                Filter::make('staff')->label('Staff only')->toggle()->query(fn (Builder $query) => $query->whereNotNull('staff_role')),
                Filter::make('closed')->label('Closed accounts')->toggle()->query(fn (Builder $query) => $query->whereNotNull('deletion_pending_at')),
                TernaryFilter::make('verified')->label('Email verified')
                    ->queries(true: fn (Builder $query) => $query->whereNotNull('email_verified_at'), false: fn (Builder $query) => $query->whereNull('email_verified_at'), blank: fn (Builder $query) => $query),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('impersonate')->label('Sign in as')->icon('heroicon-o-arrow-right-end-on-rectangle')
                    ->visible(fn (Model $record): bool => Impersonation::allowed(AdminLog::actor(), $record))
                    ->requiresConfirmation()
                    ->modalDescription('You will see the site as this person does, until you stop. It is logged, and the person can see on their Activity page that support signed in.')
                    ->action(fn (Model $record) => redirect()->to(ImpersonationController::startUrl($record))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
