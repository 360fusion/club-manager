<?php

namespace Launchpad\Admin\Resources\Staff;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\StaffAccess;
use Launchpad\Admin\UserSafeguards;
use Launchpad\Launchpad;

/** The team: who is staff, their role, and what each of them may do. Only someone who may manage staff sees it. */
class StaffResource extends Resource
{
    protected static ?string $slug = 'staff';

    protected static ?string $modelLabel = 'member of staff';

    protected static ?string $pluralModelLabel = 'staff';

    public static function getModel(): string
    {
        return Launchpad::userModel();
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Accounts';
    }

    public static function canViewAny(): bool
    {
        return StaffAccess::allowed('manage-staff');
    }

    public static function canCreate(): bool
    {
        return false; // people are added with the "Add staff" button, from the accounts that exist
    }

    public static function canEdit(Model $record): bool
    {
        return StaffAccess::allowed('manage-staff') && ! UserSafeguards::isSelf($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('staff_role');
    }

    /** @return array<string, string> key => label */
    public static function permissionOptions(): array
    {
        return collect(StaffAccess::permissions())->map(fn ($p) => $p[0])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('staff_role')->label('Role')->options(['admin' => 'Admin', 'support' => 'Support'])->required()
                ->helperText('The role sets what is ticked to begin with. Change the ticks below for this person.'),
            CheckboxList::make('permissions')->label('Permissions')->options(self::permissionOptions())
                ->descriptions(collect(StaffAccess::permissions())->map(fn ($p) => $p[1])->all())->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('staff_role')->label('Role')->badge(),
                TextColumn::make('can')->label('Can')->state(fn (Model $record): string => collect(StaffAccess::keysOf($record))->map(fn ($key) => StaffAccess::permissions()[$key][0])->implode(', '))->wrap()->limit(120),
                TextColumn::make('last_two_factor')->label('2FA')->state(fn (Model $record): string => filled($record->app_authentication_secret) ? 'On' : 'Not set up'),
            ])
            ->recordActions([
                Action::make('remove')->label('Remove from staff')->color('danger')->requiresConfirmation()
                    ->modalDescription('They become an ordinary customer again. Their account, and what they have in it, is not touched.')
                    ->visible(fn (Model $record): bool => static::canEdit($record))
                    ->action(function (Model $record) {
                        if (StaffAccess::can($record, 'manage-staff') && ! StaffAccess::someoneElseManagesStaff($record)) {
                            Notification::make()->danger()->title('Not removed')->body('Somebody must be able to manage staff.')->send();

                            return;
                        }

                        $before = ['staff_role' => $record->staff_role];
                        // the authenticator secret goes too: they set up a fresh one if they are ever added again
                        $record->forceFill(['staff_role' => null, 'staff_permissions' => null, 'app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
                        AdminLog::record('user.staff_changed', $record, "Removed {$record->email} from staff", $before, ['staff_role' => null]);
                        Notification::make()->success()->title('Removed from staff')->send();
                    }),
                EditAction::make()->modal()->visible(fn (Model $record): bool => static::canEdit($record))
                    ->mutateRecordDataUsing(fn (array $data, Model $record): array => $data + ['permissions' => StaffAccess::keysOf($record)])
                    ->using(function (Model $record, array $data): Model {
                        $before = ['staff_role' => $record->staff_role, 'staff_permissions' => $record->staff_permissions];
                        $keys = array_values(array_intersect((array) ($data['permissions'] ?? []), array_keys(StaffAccess::permissions())));

                        StaffAccess::save($record, $data['staff_role'], $keys);

                        // a team that can no longer manage itself could not put it right
                        if (! StaffAccess::someoneElseManagesStaff($record) && ! StaffAccess::can($record, 'manage-staff')) {
                            $record->forceFill($before)->save();
                            Notification::make()->danger()->title('Not changed')->body('Somebody must be able to manage staff. Leave "Manage staff" ticked for this person, or tick it for someone else first.')->send();

                            return $record;
                        }

                        AdminLog::record('user.staff_changed', $record, "Changed the role and permissions of {$record->email}", $before, ['staff_role' => $record->staff_role, 'staff_permissions' => $record->staff_permissions]);

                        return $record;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStaff::route('/')];
    }
}
