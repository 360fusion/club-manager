<?php

namespace Launchpad\Admin\Resources\Workspaces\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Launchpad\Accounts\Suspension;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\Resources\Workspaces\WorkspaceResource;
use Launchpad\Admin\StaffAccess;
use Launchpad\Workspaces\Activity;

class ViewWorkspace extends ViewRecord
{
    protected static string $resource = WorkspaceResource::class;

    protected function getHeaderActions(): array
    {
        $sso = fn () => WorkspaceResource::sso($this->getRecord());

        return [
            Action::make('suspendTeam')->label('Suspend team')->color('warning')
                ->visible(fn (): bool => $this->getRecord()->suspended_at === null && StaffAccess::allowed('close-accounts'))
                ->modalDescription('Its members see a "suspended" notice instead of the team\'s pages (billing stays open, so an owner can pay) and its API keys are refused. Nothing is deleted. They are not told the reason.')
                ->schema([Textarea::make('reason')->label('Reason (for staff)')->required()->maxLength(500)])
                ->action(function (array $data) {
                    app(Suspension::class)->suspendTeam($this->getRecord(), $data['reason']);
                    Notification::make()->success()->title('Team suspended')->send();
                }),

            Action::make('unsuspendTeam')->label('Lift suspension')
                ->visible(fn (): bool => $this->getRecord()->suspended_at !== null && StaffAccess::allowed('close-accounts'))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Suspended for: '.$this->getRecord()->suspended_reason)
                ->action(function () {
                    app(Suspension::class)->liftTeam($this->getRecord());
                    Notification::make()->success()->title('Suspension lifted')->send();
                }),

            // for a team locked out by a broken provider: its people can use passwords again, and the owner can fix the settings
            Action::make('unenforceSso')->label('Allow password sign-in')
                ->visible(fn (): bool => (bool) $sso()?->enforce && StaffAccess::allowed('manage-accounts'))
                ->requiresConfirmation()
                ->modalDescription('People on the team\'s domain can sign in with a password or Google again. Single sign-on keeps working, and the owner can require it again.')
                ->action(function () use ($sso) {
                    $connection = $sso();
                    $connection->update(['enforce' => false]);
                    AdminLog::record('sso.unenforced', $this->getRecord(), "Allowed password sign-in for {$connection->domain}");
                    Activity::record($this->getRecord(), 'sso.saved', 'stopped requiring single sign-on', 'Support');
                    Notification::make()->success()->title('Password sign-in allowed')->send();
                }),

            Action::make('removeSso')->label('Remove single sign-on')->color('danger')
                ->visible(fn (): bool => $sso() !== null && StaffAccess::allowed('manage-accounts'))
                ->requiresConfirmation()
                ->modalDescription('The team\'s connection and its verified domain are deleted. People keep their accounts and sign in with a password or Google; the owner can set single sign-on up again.')
                ->action(function () use ($sso) {
                    $connection = $sso();
                    $connection->delete();
                    AdminLog::record('sso.removed', $this->getRecord(), "Removed single sign-on for {$connection->domain}");
                    Activity::record($this->getRecord(), 'sso.removed', 'turned off single sign-on', 'Support');
                    Notification::make()->success()->title('Single sign-on removed')->send();
                }),
        ];
    }
}
