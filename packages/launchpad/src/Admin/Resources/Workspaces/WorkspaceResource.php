<?php

namespace Launchpad\Admin\Resources\Workspaces;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\StaffAccess;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\SsoConnection;
use Launchpad\Models\Workspace;
use Launchpad\Modules;

class WorkspaceResource extends Resource
{
    protected static ?string $model = Workspace::class;

    protected static ?string $slug = 'workspaces';

    protected static ?string $modelLabel = 'team';

    protected static ?string $pluralModelLabel = 'teams';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-office';
    }

    public static function canViewAny(): bool
    {
        return Modules::enabled('workspaces') && StaffAccess::allowed('view-accounts');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Accounts';
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
            Section::make('Team')->schema([
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('kind')->badge(),
                TextEntry::make('owner.email')->label('Owner'),
                TextEntry::make('plan')->label('Plan')->badge()
                    ->getStateUsing(fn (Workspace $record): string => app(Entitlements::class)->plan($record)->name),
                TextEntry::make('timezone'),
                TextEntry::make('suspended_at')->label('Suspended')->dateTime('j M Y')->color('warning')->visible(fn (Workspace $record): bool => $record->suspended_at !== null),
                TextEntry::make('suspended_reason')->label('Reason')->visible(fn (Workspace $record): bool => $record->suspended_at !== null)->columnSpan(2),
            ])->columns(3),
            Section::make('Single sign-on')->visible(fn (Workspace $record): bool => self::sso($record) !== null)->schema([
                TextEntry::make('sso_protocol')->label('Protocol')->getStateUsing(fn (Workspace $record) => self::sso($record)?->protocol === 'saml' ? 'SAML 2.0' : 'OpenID Connect'),
                TextEntry::make('sso_issuer')->label('Provider')->getStateUsing(fn (Workspace $record) => self::sso($record)?->issuer),
                TextEntry::make('sso_domain')->label('Domain')->getStateUsing(fn (Workspace $record) => self::sso($record)?->domain),
                TextEntry::make('sso_verified')->label('Domain verified')->getStateUsing(fn (Workspace $record) => self::sso($record)?->domain_verified_at?->format('j M Y') ?? 'Not yet'),
                TextEntry::make('sso_enforce')->label('Password sign-in')->getStateUsing(fn (Workspace $record) => self::sso($record)?->enforce ? 'Refused (single sign-on only)' : 'Allowed'),
            ])->columns(2),
            Section::make('Members')->schema([
                RepeatableEntry::make('memberships')->hiddenLabel()->schema([
                    TextEntry::make('user.name')->label('Name'),
                    TextEntry::make('user.email')->label('Email'),
                    TextEntry::make('role')->badge()->formatStateUsing(fn ($state) => $state->label()),
                ])->columns(3),
            ]),
        ]);
    }

    public static function sso(Workspace $record): ?SsoConnection
    {
        return SsoConnection::firstWhere('workspace_id', $record->getKey());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('owner')->withCount('memberships'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('owner.email')->label('Owner')->searchable(),
                TextColumn::make('memberships_count')->label('Members')->sortable(),
                TextColumn::make('plan')->label('Plan')->badge()->getStateUsing(fn (Workspace $record): string => app(Entitlements::class)->plan($record)->name),
                TextColumn::make('created_at')->label('Made')->dateTime('j M Y')->sortable(),
            ])
            ->filters([SelectFilter::make('kind')->options(['personal' => 'Personal', 'business' => 'Business'])])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkspaces::route('/'),
            'view' => Pages\ViewWorkspace::route('/{record}'),
        ];
    }
}
