<?php

namespace Launchpad\Admin\Resources\Staff\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\Resources\Staff\StaffResource;
use Launchpad\Launchpad;

class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')->label('Add staff')->icon('heroicon-o-user-plus')
                ->modalDescription('Pick an existing account. They sign in to the admin with the same email and password, and must set up an authenticator app.')
                ->form([
                    Select::make('user')->label('Account')->required()->searchable()
                        ->getOptionLabelUsing(fn ($value): ?string => Launchpad::userModel()::find($value)?->email)
                        ->getSearchResultsUsing(fn (string $search): array => Launchpad::userModel()::query()->whereNull('staff_role')->whereNull('deletion_pending_at')
                            ->where(fn ($q) => $q->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))->limit(20)->pluck('email', 'id')->all()),
                    Select::make('role')->options(['support' => 'Support', 'admin' => 'Admin'])->default('support')->required(),
                ])
                ->action(function (array $data) {
                    $user = Launchpad::userModel()::whereNull('staff_role')->findOrFail($data['user']);
                    $user->forceFill(['staff_role' => $data['role'], 'staff_permissions' => null])->save();
                    AdminLog::record('user.staff_changed', $user, "Made {$user->email} {$data['role']}", ['staff_role' => null], ['staff_role' => $data['role']]);
                    Notification::make()->success()->title("{$user->email} is now staff")->send();
                }),
        ];
    }
}
