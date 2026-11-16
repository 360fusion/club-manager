<?php

namespace Launchpad\Admin\Resources\WaitlistEntries;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\Waitlist;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\WaitlistEntry;

/** Who is waiting while sign-up is closed, oldest first: Invite lets them sign up and emails them the link */
class WaitlistEntryResource extends Resource
{
    protected static ?string $model = WaitlistEntry::class;

    protected static ?string $slug = 'waitlist';

    protected static ?string $pluralModelLabel = 'Waitlist';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-queue-list';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Accounts';
    }

    public static function canViewAny(): bool
    {
        return Waitlist::enabled() && StaffAccess::allowed('manage-accounts');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    private static function invite(Collection $entries): void
    {
        $entries = $entries->whereNull('invited_at');
        $entries->each(fn (WaitlistEntry $entry) => app(Waitlist::class)->invite($entry));
        AdminLog::record('waitlist.invited', null, 'Invited '.$entries->count().' from the waitlist: '.$entries->pluck('email')->take(20)->implode(', '));
        Notification::make()->success()->title($entries->count().' invited')->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->emptyStateHeading('Nobody is waiting')
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('created_at')->label('Joined')->since()->sortable(),
                TextColumn::make('invited_at')->label('Invited')->since()->placeholder('Waiting')->sortable(),
                TextColumn::make('account')->label('Signed up')->getStateUsing(fn (WaitlistEntry $record) => AccountRegistration::findByEmail($record->email) ? 'Yes' : ''),
            ])
            ->filters([TernaryFilter::make('invited_at')->label('Invited')->nullable()->default(false)])
            ->recordActions([
                Action::make('invite')->label('Invite')->visible(fn (WaitlistEntry $record) => $record->invited_at === null)->requiresConfirmation()
                    ->action(fn (WaitlistEntry $record) => self::invite(new Collection([$record]))),
            ])
            ->toolbarActions([BulkAction::make('inviteSelected')->label('Invite')->requiresConfirmation()->action(fn (Collection $records) => self::invite($records))]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageWaitlistEntries::route('/')];
    }
}
