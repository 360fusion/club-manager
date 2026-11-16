<?php

namespace Launchpad\Admin\Resources\Users\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Launchpad\Accounts\AccountClosure;
use Launchpad\Accounts\Passkeys\Passkeys;
use Launchpad\Accounts\Suspension;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\Concerns\RecordsAdminActivity;
use Launchpad\Admin\Impersonation;
use Launchpad\Admin\Resources\Users\UserResource;
use Launchpad\Admin\StaffAccess;
use Launchpad\Admin\UserSafeguards;
use Launchpad\Http\Controllers\Admin\ImpersonationController;

class EditUser extends EditRecord
{
    use RecordsAdminActivity {
        afterSave as private recordSave;
    }

    protected static string $resource = UserResource::class;

    protected function afterSave(): void
    {
        $this->recordSave();

        if ($this->getRecord()->wasChanged('staff_role')) {
            AdminLog::saved($this->getRecord(), 'user.staff_changed');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyEmail')->label('Mark email as verified')
                ->visible(fn (): bool => $this->getRecord()->email_verified_at === null)
                ->requiresConfirmation()
                ->action(function () {
                    $user = $this->getRecord();
                    $user->markEmailAsVerified();
                    $user->forceFill(['verify_by' => null])->save();
                    AdminLog::record('user.email_verified', $user, "Verified {$user->email}");
                    Notification::make()->success()->title('Marked as verified')->send();
                }),

            Action::make('impersonate')->label('Sign in as this person')->color('warning')
                ->visible(fn (): bool => Impersonation::allowed(AdminLog::actor(), $this->getRecord()))
                ->requiresConfirmation()
                ->modalDescription('You will see the site as this person does, until you stop. It is logged, and the person can see on their Activity page that support signed in.')
                ->action(fn () => redirect()->to(ImpersonationController::startUrl($this->getRecord()))),

            Action::make('sendReset')->label('Send a password reset link')
                ->visible(fn (): bool => ! $this->getRecord()->isClosed())
                ->requiresConfirmation()
                ->action(function () {
                    Password::sendResetLink(['email' => $this->getRecord()->email]);
                    Notification::make()->success()->title('Link sent')->send();
                }),

            Action::make('restore')->label('Restore account')
                ->visible(fn (): bool => $this->getRecord()->isClosed() && StaffAccess::allowed('close-accounts'))
                ->requiresConfirmation()
                ->modalDescription('The person can sign in again. What the account paid for was ended and is not restarted.')
                ->action(function () {
                    app(AccountClosure::class)->restore($this->getRecord(), AdminLog::actor());
                    Notification::make()->success()->title('Account restored')->send();
                }),

            Action::make('removePasskeys')->label('Remove passkeys')
                ->visible(fn (): bool => Passkeys::enabled() && StaffAccess::allowed('manage-accounts') && $this->getRecord()->passkeys()->exists())
                ->requiresConfirmation()
                ->modalDescription('For someone who has lost the device with their passkey on it. They sign in another way and can add a new one.')
                ->action(function () {
                    $user = $this->getRecord();
                    $count = $user->passkeys()->delete();
                    AdminLog::record('user.passkeys_removed', $user, "Removed {$count} passkeys of {$user->email}");
                    Notification::make()->success()->title('Passkeys removed')->send();
                }),

            Action::make('suspend')->label('Suspend')->color('warning')
                ->visible(fn (): bool => UserSafeguards::canSuspend($this->getRecord()))
                ->modalDescription('The person is signed out at once and cannot sign in or use an API key until the suspension is lifted. Nothing is deleted. They are not told the reason.')
                ->schema([Textarea::make('reason')->label('Reason (for staff)')->required()->maxLength(500)])
                ->action(function (array $data) {
                    app(Suspension::class)->suspend($this->getRecord(), $data['reason']);
                    Notification::make()->success()->title('Account suspended')->send();
                }),

            Action::make('unsuspend')->label('Lift suspension')
                ->visible(fn (): bool => $this->getRecord()->isSuspended() && StaffAccess::allowed('close-accounts'))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Suspended for: '.$this->getRecord()->suspended_reason)
                ->action(function () {
                    app(Suspension::class)->lift($this->getRecord());
                    Notification::make()->success()->title('Suspension lifted')->send();
                }),

            Action::make('close')->label('Close account')->color('danger')
                ->visible(fn (): bool => UserSafeguards::canClose($this->getRecord()))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'The account stops working at once and what it pays for is ended. It is kept for '.app(AccountClosure::class)->retentionDays().' days, and can be restored until then.')
                ->action(function () {
                    try {
                        app(AccountClosure::class)->close($this->getRecord(), AdminLog::actor());
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title('Not closed')->body($e->validator->errors()->first())->send();

                        return;
                    }

                    Notification::make()->success()->title('Account closed')->send();
                }),
        ];
    }
}
