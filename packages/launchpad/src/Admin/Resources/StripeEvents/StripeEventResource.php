<?php

namespace Launchpad\Admin\Resources\StripeEvents;

use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\StripeEvent;
use Launchpad\Modules;

/** The Stripe events the app has been sent. A list to look at when a payment seems not to have arrived; nothing is edited here. */
class StripeEventResource extends Resource
{
    protected static ?string $model = StripeEvent::class;

    protected static ?string $slug = 'stripe-events';

    protected static ?string $modelLabel = 'Stripe event';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bolt';
    }

    public static function canViewAny(): bool
    {
        return Modules::enabled('billing') && StaffAccess::allowed('view-billing');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Billing';
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
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Received')->dateTime('j M Y H:i:s')->sortable(),
                TextColumn::make('type')->badge()->searchable(),
                TextColumn::make('stripe_event_id')->label('Event')->copyable()->searchable(),
                TextColumn::make('processed_at')->label('Handled')->dateTime('j M Y H:i:s')->placeholder('Not yet'),
            ])
            ->recordActions([
                Action::make('stripe')->label('Open in Stripe')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (StripeEvent $record) => 'https://dashboard.stripe.com/'.(str_starts_with((string) config('cashier.key'), 'pk_test_') ? 'test/' : '')."events/{$record->stripe_event_id}", shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStripeEvents::route('/')];
    }
}
